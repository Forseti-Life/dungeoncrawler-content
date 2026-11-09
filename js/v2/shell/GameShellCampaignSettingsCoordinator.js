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
    const sourceStartFromEl = document.getElementById('campaign-source-start-from');
    const sourceThemeEl = document.getElementById('campaign-source-theme');
    const sourcePublishedDungeonEl = document.getElementById('campaign-source-published-dungeon');
    const sourceModuleReleaseEl = document.getElementById('campaign-source-module-release');
    const sourceSaveDefaultsBtn = document.getElementById('campaign-source-save-defaults');
    const sourceApplyNowBtn = document.getElementById('campaign-source-apply-now');
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
    if (sourceSaveDefaultsBtn) {
      sourceSaveDefaultsBtn.onclick = () => this.updateCampaignSourceDefaults(false);
      sourceSaveDefaultsBtn.disabled = !canManage;
    }
    if (sourceApplyNowBtn) {
      sourceApplyNowBtn.onclick = () => this.updateCampaignSourceDefaults(true);
      sourceApplyNowBtn.disabled = !canManage;
    }

    shell.campaignAccess = this.normalizeCampaignAccess({
      ...(payload?.campaign_access || {}),
      campaign_id: Number(payload?.campaign_id || shell.campaignAccess?.campaign_id || 0),
      current_mode: mode,
      can_use_player_mode: canUsePlayerMode,
      can_use_gm_mode: canUseGmMode,
    });
    shell.activeCampaignMode = mode;
    this.applyCampaignModeGates();
    this.renderCampaignSourceSelector(payload?.settings?.source_selector || {}, canManage, {
      sourceStartFromEl,
      sourceThemeEl,
      sourcePublishedDungeonEl,
      sourceModuleReleaseEl,
    });
    this.renderCampaignStorylines(payload?.settings?.storylines || {}, canManage);

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

  renderCampaignSourceSelector(selectorPayload, canManage, elements) {
    const sourceStartFromEl = elements?.sourceStartFromEl || null;
    const sourceThemeEl = elements?.sourceThemeEl || null;
    const sourcePublishedDungeonEl = elements?.sourcePublishedDungeonEl || null;
    const sourceModuleReleaseEl = elements?.sourceModuleReleaseEl || null;
    if (!sourceStartFromEl || !sourceThemeEl || !sourcePublishedDungeonEl || !sourceModuleReleaseEl) {
      return;
    }
    const defaults = selectorPayload?.defaults || {};
    const startFrom = String(defaults.start_from || 'theme').trim();
    sourceStartFromEl.value = ['theme', 'published_dungeon', 'module_release'].includes(startFrom) ? startFrom : 'theme';
    sourceThemeEl.value = String(defaults.theme || 'classic_dungeon').trim() || 'classic_dungeon';
    this.populateSelect(sourcePublishedDungeonEl, selectorPayload?.published_dungeon_options || {}, 'No published dungeons available');
    this.populateSelect(
      sourceModuleReleaseEl,
      selectorPayload?.module_release_options || {},
      'No module releases available',
      selectorPayload?.module_release_unavailable_ids || [],
    );
    sourcePublishedDungeonEl.value = String(defaults.published_dungeon || '').trim();
    sourceModuleReleaseEl.value = String(defaults.module_release || '').trim();
    sourceStartFromEl.disabled = !canManage;
    sourceThemeEl.disabled = !canManage;
    sourcePublishedDungeonEl.disabled = !canManage;
    sourceModuleReleaseEl.disabled = !canManage;
    sourceStartFromEl.dataset.canManage = canManage ? '1' : '0';
    sourceStartFromEl.onchange = () => this.applyCampaignSourceVisibility();
    this.applyCampaignSourceVisibility();
  }

  populateSelect(selectEl, optionMap, emptyLabel, unavailableIds = []) {
    if (!selectEl) return;
    const entries = Object.entries(optionMap || {});
    selectEl.innerHTML = '';
    if (!entries.length) {
      const option = document.createElement('option');
      option.value = '';
      option.textContent = emptyLabel;
      selectEl.appendChild(option);
      return;
    }
    const emptyOption = document.createElement('option');
    emptyOption.value = '';
    emptyOption.textContent = 'Select...';
    selectEl.appendChild(emptyOption);
    entries.forEach(([value, label]) => {
      const option = document.createElement('option');
      option.value = String(value);
      option.textContent = String(label);
      option.disabled = unavailableIds.includes(value);
      selectEl.appendChild(option);
    });
  }

  applyCampaignSourceVisibility() {
    const sourceStartFromEl = document.getElementById('campaign-source-start-from');
    const sourceThemeEl = document.getElementById('campaign-source-theme');
    const sourcePublishedDungeonEl = document.getElementById('campaign-source-published-dungeon');
    const sourceModuleReleaseEl = document.getElementById('campaign-source-module-release');
    const startFrom = String(sourceStartFromEl?.value || 'theme');
    const canManage = sourceStartFromEl?.dataset?.canManage === '1';
    if (sourceThemeEl) sourceThemeEl.disabled = !canManage || startFrom !== 'theme';
    if (sourcePublishedDungeonEl) sourcePublishedDungeonEl.disabled = !canManage || startFrom !== 'published_dungeon';
    if (sourceModuleReleaseEl) sourceModuleReleaseEl.disabled = !canManage || startFrom !== 'module_release';
  }

  async updateCampaignSourceDefaults(applyNow = false) {
    const shell = this.shell;
    const campaignId = Number(shell.launchContext?.campaign_id || 0);
    const statusEl = document.getElementById('campaign-settings-status');
    if (!campaignId) return;
    const startFrom = String(document.getElementById('campaign-source-start-from')?.value || 'theme').trim();
    const theme = String(document.getElementById('campaign-source-theme')?.value || 'classic_dungeon').trim();
    const publishedDungeon = String(document.getElementById('campaign-source-published-dungeon')?.value || '').trim();
    const moduleRelease = String(document.getElementById('campaign-source-module-release')?.value || '').trim();
    if (statusEl) statusEl.textContent = applyNow ? 'Applying source to campaign...' : 'Saving campaign launch defaults...';
    try {
      const response = await fetch(`/api/campaign/${campaignId}/settings/source`, {
        method: 'POST',
        headers: {
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        credentials: 'same-origin',
        body: JSON.stringify({
          start_from: startFrom,
          theme,
          published_dungeon: publishedDungeon,
          module_release: moduleRelease,
          apply_now: !!applyNow,
        }),
      });
      const payload = await response.json().catch(() => null);
      if (!response.ok || !payload?.success) {
        const error = String(payload?.error || `HTTP ${response.status}`).trim();
        if (statusEl) statusEl.textContent = `Unable to update source defaults: ${error}`;
        return;
      }
      await this.loadCampaignSettings(true);
      if (statusEl) {
        statusEl.textContent = applyNow
          ? 'Campaign source applied. Reload the room view to enter the new source dungeon.'
          : 'Campaign launch defaults saved.';
      }
    } catch (error) {
      if (statusEl) statusEl.textContent = `Unable to update source defaults: ${error?.message || 'network error'}`;
    }
  }

  renderCampaignStorylines(storylinePayload, canManage) {
        const templateSelect = document.getElementById('campaign-storyline-template');
        const primaryInput = document.getElementById('campaign-storyline-primary');
        const previewButton = document.getElementById('campaign-storyline-preview');
        const applyButton = document.getElementById('campaign-storyline-apply');
        const recheckButton = document.getElementById('campaign-storyline-recheck');
        const attachedList = document.getElementById('campaign-storyline-attached-list');
        if (!templateSelect || !primaryInput || !previewButton || !applyButton || !recheckButton || !attachedList) {
          return;
        }

        const state = this.storylineAttachmentState;
        const requestedTemplateId = typeof window !== 'undefined'
          ? String(new URLSearchParams(window.location.search).get('storyline_template_id') || '').trim()
          : '';
        const previousTemplateId = state.templateId || requestedTemplateId || templateSelect.value;
        templateSelect.replaceChildren(new Option('Select a storyline', ''));
        (Array.isArray(storylinePayload?.templates) ? storylinePayload.templates : []).forEach((template) => {
          const templateId = String(template?.template_id || '').trim();
          if (!templateId) return;
          const version = String(template?.version || '').trim();
          const label = `${String(template?.name || templateId)}${version ? ` - v${version}` : ''}`;
          templateSelect.add(new Option(label, templateId));
        });
        templateSelect.value = previousTemplateId;
        state.templateId = templateSelect.value;
        templateSelect.disabled = !canManage;
        primaryInput.disabled = !canManage;
        previewButton.disabled = !canManage;
        applyButton.disabled = !canManage;
        recheckButton.disabled = !canManage;

        templateSelect.onchange = () => {
          state.templateId = templateSelect.value;
          state.plan = null;
          state.decisions = {};
          applyButton.hidden = true;
          const planPanel = document.getElementById('campaign-storyline-plan');
          if (planPanel) planPanel.hidden = true;
        };
        previewButton.onclick = () => this.requestStorylineAttachmentPlan({});
        recheckButton.onclick = () => this.requestStorylineAttachmentPlan(this.buildStorylineAttachmentDecisions());
        applyButton.onclick = () => this.applyStorylineAttachment();

        attachedList.replaceChildren();
        const attached = Array.isArray(storylinePayload?.attached) ? storylinePayload.attached : [];
        if (!attached.length) {
          const empty = document.createElement('p');
          empty.className = 'muted';
          empty.textContent = 'No storylines are attached to this campaign.';
          attachedList.append(empty);
        } else {
          attached.forEach((storyline) => {
            const row = document.createElement('div');
            row.className = 'campaign-settings-panel__member-row';
            const name = document.createElement('strong');
            name.textContent = String(storyline?.name || storyline?.template_id || storyline?.storyline_id || 'Storyline');
            const status = document.createElement('span');
            status.className = 'pill pill-muted';
            status.textContent = String(storyline?.status || 'attached');
            const primary = document.createElement('span');
            primary.textContent = storyline?.is_primary ? 'Primary' : 'Active';
            row.append(name, status, primary);
            attachedList.append(row);
          });
        }

        if (requestedTemplateId && templateSelect.value === requestedTemplateId && !state.autoPreviewed && canManage) {
          state.autoPreviewed = true;
          void this.requestStorylineAttachmentPlan({});
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
        const templateSelect = document.getElementById('campaign-storyline-template');
        const status = document.getElementById('campaign-storyline-status');
        const templateId = String(templateSelect?.value || this.storylineAttachmentState.templateId || '').trim();
        if (!campaignId || !templateId) {
          if (status) status.textContent = 'Select a canonical storyline first.';
          return null;
        }
        this.storylineAttachmentState.templateId = templateId;
        if (status) status.textContent = 'Checking campaign gateways, canonical versions, and conflicts...';
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
              ? 'Attachment plan is ready. The storyline will activate automatically.'
              : 'Resolve the listed campaign decisions before attachment.';
          }
          return payload.plan;
        } catch (error) {
          if (status) status.textContent = `Unable to preview attachment: ${error?.message || 'network error'}`;
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
        if (status) status.textContent = 'Attaching and activating storyline...';
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
              ? 'This storyline was already attached.'
              : 'Storyline attached and activated.';
          }
        } catch (error) {
          if (status) status.textContent = `Unable to attach storyline: ${error?.message || 'network error'}`;
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
