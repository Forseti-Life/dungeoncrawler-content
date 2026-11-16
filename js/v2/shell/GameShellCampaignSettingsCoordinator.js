/**
 * Owns campaign settings tab data load/render/update flows.
 */
export class GameShellCampaignSettingsCoordinator {
  constructor(shell) {
    this.shell = shell;
    this.storylineAttachmentState = {
      templateId: '',
      plan: null,
      decisions: {},
      autoPreviewed: false,
    };
    this.csrfTokenPromise = null;
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
    this.renderCampaignLaunchSummary(payload?.settings?.launch);
    this.renderCampaignStorylines(payload?.settings?.storylines || {}, canManage);

    this.renderCampaignMembers(Array.isArray(payload?.members) ? payload.members : [], Number(payload?.membership?.uid || 0));
    this.bindMemberCandidateFilter();
    this.renderCampaignDiscord(payload?.discord || {});

    if (statusEl) {
      statusEl.textContent = '';
    }
  }

  renderCampaignMembers(members, currentUid) {
    const memberListEl = document.getElementById('campaign-member-list');
    if (!memberListEl) return;
    memberListEl.innerHTML = '';
    members.forEach((member) => {
      const uid = Number(member.uid);
      const row = document.createElement('div');
      row.className = 'campaign-settings-panel__member-row';

      const identity = document.createElement('div');
      const identityName = document.createElement('strong');
      identityName.textContent = uid === currentUid ? `${member.display_name} (you)` : member.display_name;
      const identityMeta = document.createElement('p');
      identityMeta.className = 'campaign-settings-panel__member-meta';
      const character = member.character_name ? `Playing ${member.character_name}` : 'No character chosen yet';
      identityMeta.textContent = member.added_by_name ? `${character} · added by ${member.added_by_name}` : character;
      identity.append(identityName, identityMeta);

      const removeBtn = document.createElement('button');
      removeBtn.type = 'button';
      removeBtn.className = 'btn btn-secondary btn-sm';
      removeBtn.textContent = uid === currentUid ? 'Leave' : 'Remove';
      if (members.length === 1) {
        removeBtn.disabled = true;
        removeBtn.title = 'A campaign keeps at least one player.';
      }
      removeBtn.onclick = () => this.changeCampaignMembers(`/members/${uid}/remove`, null, uid === currentUid);

      row.append(identity, removeBtn);
      memberListEl.appendChild(row);
    });
  }

  bindMemberCandidateFilter() {
    const filterEl = document.getElementById('campaign-member-filter');
    if (!filterEl || filterEl.dataset.bound === '1') {
      void this.loadMemberCandidates();
      return;
    }
    filterEl.dataset.bound = '1';
    let timer = null;
    filterEl.addEventListener('input', () => {
      clearTimeout(timer);
      timer = setTimeout(() => this.loadMemberCandidates(), 200);
    });
    void this.loadMemberCandidates();
  }

  async loadMemberCandidates() {
    const campaignId = Number(this.shell.launchContext?.campaign_id || 0);
    const filterEl = document.getElementById('campaign-member-filter');
    const listEl = document.getElementById('campaign-member-candidates');
    if (!campaignId || !filterEl || !listEl) return;
    const filter = filterEl.value.trim();
    const response = await fetch(`/api/campaign/${campaignId}/members/candidates?q=${encodeURIComponent(filter)}`, {
      credentials: 'same-origin',
      headers: { Accept: 'application/json' },
    });
    const payload = await response.json().catch(() => null);
    if (filterEl.value.trim() !== filter) return;
    listEl.innerHTML = '';
    if (!response.ok || !payload?.success) {
      this.setMemberStatus(`Unable to load users: ${payload?.error || `HTTP ${response.status}`}`);
      return;
    }
    if (!payload.candidates.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = filter ? `No other users match “${filter}”.` : 'Every user is already in this campaign.';
      listEl.appendChild(empty);
      return;
    }
    payload.candidates.forEach((candidate) => {
      const row = document.createElement('div');
      row.className = 'campaign-settings-panel__member-row';
      const name = document.createElement('strong');
      name.textContent = candidate.display_name;
      const addBtn = document.createElement('button');
      addBtn.type = 'button';
      addBtn.className = 'btn btn-primary btn-sm';
      addBtn.textContent = 'Add';
      addBtn.onclick = () => this.changeCampaignMembers('/members', { uid: Number(candidate.uid) }, false);
      row.append(name, addBtn);
      listEl.appendChild(row);
    });
    if (payload.candidates.length === Number(payload.limit)) {
      const more = document.createElement('p');
      more.className = 'muted';
      more.textContent = `Showing the first ${payload.limit}. Type a name to narrow the list.`;
      listEl.appendChild(more);
    }
  }

  setMemberStatus(message) {
    const statusEl = document.getElementById('campaign-member-status');
    if (statusEl) statusEl.textContent = message;
  }

  async changeCampaignMembers(path, body, leaving) {
    const campaignId = Number(this.shell.launchContext?.campaign_id || 0);
    if (!campaignId) return;
    this.setMemberStatus('Saving…');
    const response = await fetch(`/api/campaign/${campaignId}${path}`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': await this.getCsrfToken(),
      },
      credentials: 'same-origin',
      body: JSON.stringify(body || {}),
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.success) {
      this.setMemberStatus(`Not saved: ${payload?.error || `HTTP ${response.status}`}`);
      return;
    }
    if (leaving) {
      window.location.assign('/campaigns');
      return;
    }
    this.setMemberStatus('Saved.');
    await this.loadCampaignSettings(true);
  }

  renderCampaignDiscord(discord) {
    const el = document.getElementById('campaign-discord');
    if (!el) return;
    el.innerHTML = '';

    const serverRow = document.createElement('div');
    serverRow.className = 'campaign-settings-panel__member-row';
    const serverLabel = document.createElement('strong');
    serverLabel.textContent = 'Community Discord';
    serverRow.appendChild(serverLabel);
    if (discord.invite_url) {
      serverRow.appendChild(this.externalLink(discord.invite_url, 'Open our Discord server'));
    } else {
      const missing = document.createElement('span');
      missing.className = 'muted';
      missing.textContent = 'Waiting on the site administrator: the Discord general-channel link has not been set yet.';
      serverRow.appendChild(missing);
    }
    el.appendChild(serverRow);

    const manualNote = document.createElement('p');
    manualNote.className = 'muted';
    manualNote.textContent = 'Create your own voice channels directly on the Discord server. Automatic campaign voice-channel creation is deferred.';
    el.appendChild(manualNote);
    if (!discord.invite_url && discord.settings_url) {
      el.appendChild(this.externalLink(discord.settings_url, 'Configure the Discord general-channel link'));
    }
  }

  externalLink(url, text) {
    const link = document.createElement('a');
    link.href = url;
    link.target = '_blank';
    link.rel = 'noopener noreferrer';
    link.textContent = text;
    return link;
  }

  async createDiscordVoiceChannel(button) {
    const campaignId = Number(this.shell.launchContext?.campaign_id || 0);
    const statusEl = document.getElementById('campaign-discord-status');
    if (!campaignId) return;
    button.disabled = true;
    if (statusEl) statusEl.textContent = 'Waiting on Discord to create the channel…';
    const response = await fetch(`/api/campaign/${campaignId}/discord/voice-channel`, {
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'X-CSRF-Token': await this.getCsrfToken(),
      },
      credentials: 'same-origin',
      body: '{}',
    });
    const payload = await response.json().catch(() => null);
    if (!response.ok || !payload?.success) {
      if (statusEl) statusEl.textContent = `Not created: ${payload?.error || `HTTP ${response.status}`}`;
      button.disabled = false;
      return;
    }
    if (statusEl) statusEl.textContent = 'Voice channel created.';
    this.renderCampaignDiscord(payload.discord);
  }

  renderCampaignLaunchSummary(launch) {
    const el = document.getElementById('campaign-settings-launch');
    if (!el || !launch) return;
    const created = new Date(Number(launch.created) * 1000).toLocaleDateString(undefined, { year: 'numeric', month: 'short', day: 'numeric' });
    el.textContent = `Theme: ${launch.theme} · Difficulty: ${launch.difficulty} · Created ${created} by ${launch.created_by}. These were set when the campaign was created and cannot be changed.`;
  }

  renderCampaignStorylines(storylinePayload, canManage) {
    const attachedList = document.getElementById('campaign-storyline-attached-list');
    const availableList = document.getElementById('campaign-storyline-available-list');
    const applyButton = document.getElementById('campaign-storyline-apply');
    const recheckButton = document.getElementById('campaign-storyline-recheck');
    const cancelButton = document.getElementById('campaign-storyline-cancel');
    if (!attachedList || !availableList || !applyButton || !recheckButton || !cancelButton) {
      return;
    }

    const state = this.storylineAttachmentState;
    const attached = Array.isArray(storylinePayload?.attached) ? storylinePayload.attached : [];
    const attachedTemplateIds = new Set(attached.map((storyline) => String(storyline.template_id)));
    const available = (Array.isArray(storylinePayload?.templates) ? storylinePayload.templates : [])
      .filter((template) => !attachedTemplateIds.has(String(template.template_id)));

    document.getElementById('campaign-storyline-attached-count').textContent = `(${attached.length})`;
    document.getElementById('campaign-storyline-available-count').textContent = `(${available.length})`;

    attachedList.replaceChildren();
    if (!attached.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'This campaign has no storylines yet.';
      attachedList.append(empty);
    }
    attached.forEach((storyline) => {
      const row = document.createElement('div');
      row.className = 'campaign-settings-panel__member-row';
      const name = document.createElement('strong');
      name.textContent = storyline.name;
      const status = document.createElement('span');
      status.className = 'pill pill-muted';
      status.textContent = {
        available: 'Not started',
        lead: 'Not started',
        bootstrapping: 'Preparing',
        active: 'In progress',
        completed: 'Completed',
      }[String(storyline.status)] || String(storyline.status);
      row.dataset.filterText = String(storyline.name).toLowerCase();
      row.append(name, status);
      if (storyline.is_primary) {
        const primary = document.createElement('span');
        primary.className = 'pill pill-strong';
        primary.textContent = 'Primary';
        row.append(primary);
      }
      attachedList.append(row);
    });

    availableList.replaceChildren();
    if (!available.length) {
      const empty = document.createElement('p');
      empty.className = 'muted';
      empty.textContent = 'Every published storyline is already in this campaign.';
      availableList.append(empty);
    }
    const startAdding = (template) => {
      state.templateId = String(template.template_id);
      state.plan = null;
      state.decisions = {};
      applyButton.hidden = true;
      document.getElementById('campaign-storyline-plan').hidden = true;
      document.getElementById('campaign-storyline-add-title').textContent = `Adding: ${template.name}`;
      document.getElementById('campaign-storyline-add').hidden = false;
      void this.requestStorylineAttachmentPlan({});
    };
    available.forEach((template) => {
      const row = document.createElement('div');
      row.className = 'campaign-settings-panel__member-row';
      const identity = document.createElement('div');
      const name = document.createElement('strong');
      name.textContent = template.version ? `${template.name} (v${template.version})` : template.name;
      const synopsis = document.createElement('p');
      synopsis.className = 'campaign-settings-panel__member-meta';
      synopsis.textContent = template.synopsis;
      identity.append(name, synopsis);
      const addButton = document.createElement('button');
      addButton.type = 'button';
      addButton.className = 'btn btn-primary btn-sm';
      addButton.textContent = 'Add';
      addButton.disabled = !canManage;
      addButton.onclick = () => startAdding(template);
      row.dataset.filterText = `${template.name} ${template.synopsis}`.toLowerCase();
      row.append(identity, addButton);
      availableList.append(row);
    });

    const filterInput = document.getElementById('campaign-storyline-filter');
    const applyFilter = () => {
      const needle = filterInput.value.trim().toLowerCase();
      [[attachedList, 'campaign-storyline-attached-count', attached.length], [availableList, 'campaign-storyline-available-count', available.length]].forEach(([list, countId, total]) => {
        let shown = 0;
        list.querySelectorAll('[data-filter-text]').forEach((row) => {
          row.hidden = !row.dataset.filterText.includes(needle);
          if (!row.hidden) shown += 1;
        });
        list.querySelector('[data-filter-empty]')?.remove();
        if (needle && total > 0 && shown === 0) {
          const none = document.createElement('p');
          none.className = 'muted';
          none.dataset.filterEmpty = '1';
          none.textContent = `None match “${filterInput.value.trim()}”.`;
          list.append(none);
        }
        document.getElementById(countId).textContent = needle ? `(${shown} of ${total})` : `(${total})`;
      });
    };
    filterInput.oninput = applyFilter;
    applyFilter();

    const addPanel = document.getElementById('campaign-storyline-add');
    if (!available.some((template) => String(template.template_id) === state.templateId)) {
      state.templateId = '';
      addPanel.hidden = true;
    }
    recheckButton.onclick = () => this.requestStorylineAttachmentPlan(this.buildStorylineAttachmentDecisions());
    applyButton.onclick = () => this.applyStorylineAttachment();
    cancelButton.onclick = () => {
      state.templateId = '';
      state.plan = null;
      state.decisions = {};
      addPanel.hidden = true;
      document.getElementById('campaign-storyline-status').textContent = '';
    };

    const requestedTemplateId = String(new URLSearchParams(window.location.search).get('storyline_template_id') || '').trim();
    const requested = available.find((template) => String(template.template_id) === requestedTemplateId);
    if (requested && !state.autoPreviewed && canManage) {
      state.autoPreviewed = true;
      startAdding(requested);
    }
  }

      async getCsrfToken() {
        this.csrfTokenPromise ||= fetch('/session/token', {
          credentials: 'same-origin',
        }).then((response) => {
          if (!response.ok) {
            throw new Error(`Unable to load CSRF token: HTTP ${response.status}`);
          }
          return response.text();
        }).then((token) => token.trim());
        return this.csrfTokenPromise;
      }

      buildStorylineAttachmentDecisions() {
        const state = this.storylineAttachmentState;
        const decisions = { ...state.decisions };
        const gatewayRoom = String(document.getElementById('campaign-storyline-gateway-room')?.value || '').trim();
        const gatewayPort = String(document.getElementById('campaign-storyline-gateway-port')?.value || '').trim();
        const travelDistance = Number(document.getElementById('campaign-storyline-travel-distance')?.value || 0);
        if (gatewayRoom) decisions.campaign_gateway_room_id = gatewayRoom;
        if (gatewayPort) decisions.campaign_gateway_port_id = gatewayPort;
        if (travelDistance > 0) decisions.travel_distance = travelDistance;
        const settingRows = document.querySelectorAll('[data-campaign-storyline-setting]');
        if (settingRows.length > 0) {
          decisions.setting_placements = {};
          settingRows.forEach((row) => {
            const dungeonId = String(row.dataset.campaignStorylineSetting || '').trim();
            const entryRoomId = String(row.querySelector('[data-setting-entry-room]')?.value || '').trim();
            const settingGatewayRoomId = String(row.querySelector('[data-setting-gateway-room]')?.value || '').trim();
            const settingGatewayPortId = String(row.querySelector('[data-setting-gateway-port]')?.value || '').trim();
            const settingTravelDistance = Number(row.querySelector('[data-setting-travel-distance]')?.value || 0);
            if (dungeonId) {
              decisions.setting_placements[dungeonId] = {
                entry_room_id: entryRoomId,
                campaign_gateway_room_id: settingGatewayRoomId,
                campaign_gateway_port_id: settingGatewayPortId,
                travel_distance: settingTravelDistance,
              };
            }
          });
        }
        decisions.make_primary = document.getElementById('campaign-storyline-primary')?.checked === true;

        const actorRows = document.querySelectorAll('[data-campaign-storyline-actor]');
        if (actorRows.length > 0) {
          decisions.actors = {};
          actorRows.forEach((row) => {
            const entityRef = String(row.dataset.campaignStorylineActor || '').trim();
            const action = String(row.querySelector('[data-campaign-storyline-actor-action]')?.value || '').trim();
            const runtimeInstanceId = action === 'map'
              ? String(row.querySelector('[data-campaign-storyline-actor-map]')?.value || '').trim()
              : String(row.querySelector('[data-campaign-storyline-actor-clone]')?.value || '').trim();
            if (entityRef && action && runtimeInstanceId) {
              decisions.actors[entityRef] = {
                action,
                runtime_instance_id: runtimeInstanceId,
              };
            }
          });
        }
        const primaryFinding = state.plan?.findings?.find(
          (finding) => finding.code === 'campaign_primary_storyline_replacement_confirmation_required',
        );
        if (decisions.make_primary && primaryFinding?.evidence?.current_primary_storyline_id) {
          decisions.replace_primary_storyline_id = primaryFinding.evidence.current_primary_storyline_id;
        }
        return decisions;
      }

      async requestStorylineAttachmentPlan(decisions = {}) {
        const campaignId = Number(this.shell.launchContext?.campaign_id || 0);
        const status = document.getElementById('campaign-storyline-status');
        const templateId = this.storylineAttachmentState.templateId;
        if (!campaignId || !templateId) {
          return null;
        }
        if (status) status.textContent = 'Checking how this storyline fits the campaign…';
        try {
          const response = await fetch(`/api/campaign/${campaignId}/storylines/attachment-plan`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': await this.getCsrfToken(),
            },
            body: JSON.stringify({ template_id: templateId, decisions }),
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload?.plan) {
            throw new Error(payload?.error || `HTTP ${response.status}`);
          }
          this.storylineAttachmentState.decisions = decisions;
          this.renderStorylineAttachmentPlan(payload.plan);
          if (status) {
            status.textContent = payload.plan.can_apply
              ? 'Ready to add. The storyline starts as soon as it is added.'
              : 'Resolve the listed decisions before this storyline can be added.';
          }
          return payload.plan;
        } catch (error) {
          if (status) status.textContent = `Unable to check this storyline: ${error?.message || 'network error'}`;
          return null;
        }
      }

      renderStorylineAttachmentPlan(plan) {
        const state = this.storylineAttachmentState;
        state.plan = plan;
        const planPanel = document.getElementById('campaign-storyline-plan');
        const summary = document.getElementById('campaign-storyline-plan-summary');
        const hook = document.getElementById('campaign-storyline-hook');
        const findings = document.getElementById('campaign-storyline-findings');
        const gatewayRoomSelect = document.getElementById('campaign-storyline-gateway-room');
        const gatewayPortSelect = document.getElementById('campaign-storyline-gateway-port');
        const travelDistanceInput = document.getElementById('campaign-storyline-travel-distance');
        const settingPlacements = document.getElementById('campaign-storyline-setting-placements');
        const actorDecisions = document.getElementById('campaign-storyline-actor-decisions');
        const applyButton = document.getElementById('campaign-storyline-apply');
        if (!planPanel || !summary || !hook || !findings || !gatewayRoomSelect || !gatewayPortSelect || !travelDistanceInput || !settingPlacements || !actorDecisions || !applyButton) {
          return;
        }
        planPanel.hidden = false;
        summary.textContent = `${plan.counts?.errors || 0} blocking errors, ${plan.counts?.decisions || 0} decisions, ${plan.counts?.operations || 0} planned operations.`;
        const hookName = String(plan.storyline_hook?.source_room_id || '').trim();
        hook.textContent = hookName
          ? `Authored storyline hook: ${hookName}. Campaign attachment does not replace this room.`
          : 'The storyline hook contract is unresolved.';

        const selectedGatewayRoomId = String(
          plan.campaign_gateway?.room_id
          || state.decisions.campaign_gateway_room_id
          || ''
        ).trim();
        gatewayRoomSelect.replaceChildren(new Option('Select a campaign gateway room', ''));
        const gatewayRooms = Array.isArray(plan.campaign_options?.gateway_rooms)
          ? plan.campaign_options.gateway_rooms
          : [];
        gatewayRooms.forEach((room) => {
          const roles = Array.isArray(room?.roles) && room.roles.length ? ` - ${room.roles.join(', ')}` : '';
          const suffix = room?.is_default ? ' (default)' : roles;
          gatewayRoomSelect.add(new Option(`${room?.name || room?.room_id}${suffix}`, String(room?.room_id || '')));
        });
        gatewayRoomSelect.value = selectedGatewayRoomId;
        gatewayRoomSelect.onchange = () => {
          state.decisions.campaign_gateway_room_id = gatewayRoomSelect.value;
          delete state.decisions.campaign_gateway_port_id;
          void this.requestStorylineAttachmentPlan(this.buildStorylineAttachmentDecisions());
        };

        const selectedGateway = gatewayRooms.find(
          (room) => String(room?.room_id || '') === gatewayRoomSelect.value,
        ) || null;
        gatewayPortSelect.replaceChildren(new Option('Select a gateway exit', ''));
        (Array.isArray(selectedGateway?.ports) ? selectedGateway.ports : []).forEach((port) => {
          gatewayPortSelect.add(new Option(String(port?.label || port?.port_id || 'Gateway exit'), String(port?.port_id || '')));
        });
        gatewayPortSelect.value = String(
          plan.campaign_gateway?.port_id
          || state.decisions.campaign_gateway_port_id
          || ''
        ).trim();
        gatewayPortSelect.onchange = () => {
          state.decisions.campaign_gateway_port_id = gatewayPortSelect.value;
        };
        travelDistanceInput.value = String(
          plan.campaign_gateway?.travel_distance
          || state.decisions.travel_distance
          || ''
        );
        travelDistanceInput.onchange = () => {
          state.decisions.travel_distance = Number(travelDistanceInput.value || 0);
        };

        settingPlacements.replaceChildren();
        (Array.isArray(plan.setting_placements) ? plan.setting_placements : [])
          .filter((placement) => placement?.is_storyline_hook !== true)
          .forEach((placement) => {
            const dungeonId = String(placement?.source_dungeon_id || '').trim();
            const existing = state.decisions.setting_placements?.[dungeonId] || {};
            const row = document.createElement('fieldset');
            row.dataset.campaignStorylineSetting = dungeonId;
            const legend = document.createElement('legend');
            legend.textContent = `Separate setting: ${dungeonId}`;
            const entry = document.createElement('select');
            entry.dataset.settingEntryRoom = '';
            entry.add(new Option('Select setting entry room', ''));
            (Array.isArray(placement?.room_options) ? placement.room_options : []).forEach((roomId) => {
              entry.add(new Option(String(roomId), String(roomId)));
            });
            entry.value = String(placement?.entry_source_room_id || existing.entry_room_id || '');
            const room = document.createElement('select');
            room.dataset.settingGatewayRoom = '';
            room.add(new Option('Select campaign road gateway', ''));
            gatewayRooms.forEach((option) => {
              room.add(new Option(String(option?.name || option?.room_id), String(option?.room_id || '')));
            });
            room.value = String(placement?.campaign_gateway_room_id || existing.campaign_gateway_room_id || '');
            const port = document.createElement('select');
            port.dataset.settingGatewayPort = '';
            const renderPorts = () => {
              port.replaceChildren(new Option('Select gateway exit', ''));
              const option = gatewayRooms.find((candidate) => String(candidate?.room_id || '') === room.value);
              (Array.isArray(option?.ports) ? option.ports : []).forEach((candidate) => {
                port.add(new Option(String(candidate?.label || candidate?.port_id), String(candidate?.port_id || '')));
              });
              port.value = String(placement?.campaign_gateway_port_id || existing.campaign_gateway_port_id || '');
            };
            renderPorts();
            room.onchange = () => {
              renderPorts();
              port.value = '';
            };
            const distance = document.createElement('input');
            distance.type = 'number';
            distance.min = '1';
            distance.step = '1';
            distance.placeholder = 'Road travel distance';
            distance.dataset.settingTravelDistance = '';
            distance.value = String(placement?.travel_distance || existing.travel_distance || '');
            row.append(legend, entry, room, port, distance);
            settingPlacements.append(row);
          });

        findings.replaceChildren();
        const findingList = document.createElement('ul');
        findingList.className = 'campaign-storyline-settings__findings';
        (Array.isArray(plan.findings) ? plan.findings : []).forEach((finding) => {
          const item = document.createElement('li');
          item.className = `campaign-storyline-settings__finding--${String(finding?.severity || 'info')}`;
          item.textContent = `${String(finding?.asset_type || 'attachment')}: ${String(finding?.message || '')}`;
          findingList.append(item);
        });
        if (findingList.childElementCount > 0) findings.append(findingList);

        actorDecisions.replaceChildren();
        const actorFindings = (Array.isArray(plan.required_decisions) ? plan.required_decisions : []).filter(
          (finding) => finding?.code === 'campaign_actor_mapping_required',
        );
        actorFindings.forEach((finding) => {
          const entityRef = String(finding?.asset_id || '').trim();
          const existing = state.decisions.actors?.[entityRef] || {};
          const row = document.createElement('fieldset');
          row.className = 'campaign-storyline-settings__actor';
          row.dataset.campaignStorylineActor = entityRef;
          const legend = document.createElement('legend');
          legend.textContent = `Actor conflict: ${entityRef}`;
          const action = document.createElement('select');
          action.dataset.campaignStorylineActorAction = '';
          action.add(new Option('Select map or copy', ''));
          action.add(new Option('Map to existing campaign actor', 'map'));
          action.add(new Option('Create a separate storyline copy', 'clone'));
          action.value = String(existing.action || '');
          const map = document.createElement('select');
          map.dataset.campaignStorylineActorMap = '';
          map.add(new Option('Select an actor', ''));
          (Array.isArray(plan.campaign_options?.actors) ? plan.campaign_options.actors : []).forEach((actor) => {
            map.add(new Option(String(actor?.name || actor?.runtime_instance_id), String(actor?.runtime_instance_id || '')));
          });
          map.value = existing.action === 'map' ? String(existing.runtime_instance_id || '') : '';
          const clone = document.createElement('input');
          clone.type = 'text';
          clone.dataset.campaignStorylineActorClone = '';
          clone.value = existing.action === 'clone'
            ? String(existing.runtime_instance_id || '')
            : String(finding?.evidence?.suggested_clone_instance_id || '');
          const toggle = () => {
            map.hidden = action.value !== 'map';
            clone.hidden = action.value !== 'clone';
          };
          action.onchange = toggle;
          toggle();
          row.append(legend, action, map, clone);
          actorDecisions.append(row);
        });
        applyButton.hidden = plan.can_apply !== true;
      }

      async applyStorylineAttachment() {
        const state = this.storylineAttachmentState;
        const campaignId = Number(this.shell.launchContext?.campaign_id || 0);
        const status = document.getElementById('campaign-storyline-status');
        const applyButton = document.getElementById('campaign-storyline-apply');
        const decisions = this.buildStorylineAttachmentDecisions();
        const plan = await this.requestStorylineAttachmentPlan(decisions);
        if (!plan?.can_apply || !campaignId || !state.templateId) {
          return;
        }
        if (applyButton) applyButton.disabled = true;
        if (status) status.textContent = 'Adding the storyline…';
        try {
          const response = await fetch(`/api/campaign/${campaignId}/storylines/attachment-apply`, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': await this.getCsrfToken(),
            },
            body: JSON.stringify({
              template_id: state.templateId,
              plan_id: plan.plan_id,
              decisions,
            }),
          });
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload?.success) {
            if (payload?.plan) this.renderStorylineAttachmentPlan(payload.plan);
            throw new Error(payload?.error || `HTTP ${response.status}`);
          }
          state.plan = null;
          state.decisions = {};
          await this.loadCampaignSettings(true);
          if (status) {
            status.textContent = payload?.attachment?.idempotent
              ? 'This storyline was already in the campaign.'
              : 'Storyline added to the campaign.';
          }
        } catch (error) {
          if (status) status.textContent = `Unable to add the storyline: ${error?.message || 'network error'}`;
        } finally {
          if (applyButton) applyButton.disabled = false;
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
}
