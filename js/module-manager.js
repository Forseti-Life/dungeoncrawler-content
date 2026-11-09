/**
 * @file
 * Drupal behavior bootstrap for module manager read-only intake workflows.
 */

(function (Drupal, drupalSettings, once) {
  'use strict';

  function createEmptyItem(message) {
    const li = document.createElement('li');
    li.className = 'module-manager__item module-manager__item--empty';
    li.textContent = message;
    return li;
  }

  function clearChildren(element) {
    while (element.firstChild) {
      element.removeChild(element.firstChild);
    }
  }

  function renderList(element, items) {
    clearChildren(element);
    if (!Array.isArray(items) || items.length === 0) {
      element.appendChild(createEmptyItem('No entries.'));
      return;
    }
    items.forEach((item) => {
      element.appendChild(item);
    });
  }

  function catalogItem(reference) {
    const li = document.createElement('li');
    li.className = 'module-manager__item';
    li.dataset.access = String(reference.access || 'unknown');

    const heading = document.createElement('p');
    heading.className = 'module-manager__item-title';
    heading.textContent = `${reference.title || reference.package_id || 'Untitled'} (${reference.system || 'unknown'})`;

    const meta = document.createElement('p');
    meta.className = 'module-manager__item-meta';
    meta.textContent = `id=${reference.package_id || 'n/a'} • access=${reference.access || 'unknown'} • v${reference.version || 'n/a'}`;

    li.append(heading, meta);
    return li;
  }

  function packItem(pack) {
    const li = document.createElement('li');
    li.className = 'module-manager__item';
    const title = document.createElement('p');
    title.className = 'module-manager__item-title';
    title.textContent = `${pack.pack_name || 'unnamed'} (${pack.format || 'unknown'})`;
    const meta = document.createElement('p');
    meta.className = 'module-manager__item-meta';
    meta.textContent = `status=${pack.status || 'unknown'} • docs=${pack.document_count ?? 0}`;
    li.append(title, meta);
    return li;
  }

  function findingItem(finding) {
    const li = document.createElement('li');
    li.className = 'module-manager__item';
    li.dataset.severity = String(finding.severity || 'error');
    const message = document.createElement('p');
    message.className = 'module-manager__item-title';
    message.textContent = `${finding.code || 'finding'}: ${finding.message || 'Unknown issue'}`;
    if (finding.pointer || (finding.context && typeof finding.context === 'object')) {
      const meta = document.createElement('p');
      meta.className = 'module-manager__item-meta';
      const context = finding.context && typeof finding.context === 'object'
        ? JSON.stringify(finding.context)
        : '';
      meta.textContent = `pointer=${finding.pointer || 'n/a'}${context ? ` • context=${context}` : ''}`;
      li.appendChild(meta);
    }
    li.appendChild(message);
    return li;
  }

  function recommendationItem(text) {
    const li = document.createElement('li');
    li.className = 'module-manager__item';
    const body = document.createElement('p');
    body.className = 'module-manager__item-meta';
    body.textContent = text;
    li.appendChild(body);
    return li;
  }

  function formatBytes(bytes) {
    const value = Number(bytes);
    if (!Number.isFinite(value) || value < 0) {
      return 'n/a';
    }
    if (value < 1024) {
      return `${value} B`;
    }
    const kib = value / 1024;
    if (kib < 1024) {
      return `${kib.toFixed(1)} KiB`;
    }
    const mib = kib / 1024;
    if (mib < 1024) {
      return `${mib.toFixed(1)} MiB`;
    }
    return `${(mib / 1024).toFixed(2)} GiB`;
  }

  function moduleOverviewItem(item, onInspect) {
    const li = document.createElement('li');
    li.className = 'module-manager__item module-manager__item--overview';
    const title = document.createElement('p');
    title.className = 'module-manager__item-title';
    const label = item.manifest?.title || item.manifest?.id || item.name || 'Untitled module';
    const version = item.manifest?.version ? ` v${item.manifest.version}` : '';
    title.textContent = `${label}${version}`;
    li.appendChild(title);

    if (item.error?.code) {
      li.dataset.severity = 'error';
      const error = document.createElement('p');
      error.className = 'module-manager__item-meta';
      error.textContent = `Scoring unavailable: ${item.error.code}`;
      li.appendChild(error);
    }
    else {
      const analysis = item.analysis || {};
      const counts = analysis.counts || {};
      const signals = analysis.signals || {};
      const score = document.createElement('p');
      score.className = 'module-manager__item-meta module-manager__overview-score';
      score.dataset.state = String(analysis.state || 'unknown');
      score.textContent = `Score ${analysis.score ?? 'n/a'}/100 • Grade ${analysis.grade || 'n/a'} • ${analysis.state || 'unknown'}`;
      const breakdown = document.createElement('p');
      breakdown.className = 'module-manager__item-meta';
      breakdown.textContent = `dungeons=${counts.dungeons ?? 0}, rooms=${counts.rooms ?? 0}, actors=${counts.actors ?? 0}, items=${counts.items ?? 0}, journal_quest_items=${counts.journal_quest_items ?? 0}, unsupported=${counts.unsupported ?? 0}, requires_authoring=${counts.requires_authoring ?? 0}, gaps=${counts.generation_gaps ?? 0}, authoring_pressure=${signals.authoring_pressure_pct ?? 0}%`;
      const summary = document.createElement('p');
      summary.className = 'module-manager__item-meta';
      summary.textContent = analysis.summary || '';
      li.append(score, breakdown, summary);
      const rows = analysis?.object_inventory?.rows;
      if (Array.isArray(rows) && rows.length) {
        li.appendChild(createInventoryTable(rows));
      }
    }

    const meta = document.createElement('p');
    meta.className = 'module-manager__item-meta';
    const hashLabel = item.sha256 ? item.sha256.slice(0, 16) : 'n/a';
    meta.textContent = `${item.name || 'archive'} • ${formatBytes(item.bytes)} • sha256=${hashLabel}…`;
    li.appendChild(meta);

    const inspect = document.createElement('button');
    inspect.type = 'button';
    inspect.textContent = 'Inspect this module';
    inspect.addEventListener('click', () => onInspect(item));
    li.appendChild(inspect);
    return li;
  }

  function createInventoryTable(rows) {
    const wrap = document.createElement('div');
    wrap.className = 'module-manager__inventory-wrap';
    const table = document.createElement('table');
    table.className = 'module-manager__inventory-table';
    const thead = document.createElement('thead');
    const headRow = document.createElement('tr');
    ['Category', 'Foundry VTT object', 'Foundry count', 'Forseti object', 'Forseti count', 'Notes'].forEach((label) => {
      const th = document.createElement('th');
      th.textContent = label;
      headRow.appendChild(th);
    });
    thead.appendChild(headRow);
    const tbody = document.createElement('tbody');
    rows.forEach((row) => {
      const tr = document.createElement('tr');
      const cells = [
        row.label || 'n/a',
        row.foundry_object || 'n/a',
        String(row.foundry_count ?? 0),
        row.forseti_object || 'n/a',
        String(row.forseti_count ?? 0),
        row.note || '',
      ];
      cells.forEach((value) => {
        const td = document.createElement('td');
        td.textContent = value;
        tr.appendChild(td);
      });
      tbody.appendChild(tr);
    });
    table.append(thead, tbody);
    wrap.appendChild(table);
    return wrap;
  }

  function releaseItem(release, checkCoverage, selectRelease) {
    const li = document.createElement('li');
    li.className = 'module-manager__item';
    li.dataset.status = String(release.publication_status || 'unknown');
    li.dataset.launchable = release.launchable ? '1' : '0';
    const title = document.createElement('p');
    title.className = 'module-manager__item-title';
    title.textContent = `${release.module_title || release.module_id || 'Untitled'} v${release.module_version || 'n/a'}`;
    const mapping = release.mapping || {};
    const mappingLabel = mapping.dungeon_id && mapping.version_id
      ? `${mapping.dungeon_id}@${mapping.version_id}`
      : 'unmapped';
    const receiptSummary = mapping.native_receipt_summary || {};
    const executionSummary = mapping.execution_summary || {};
    const receiptLabel = `${receiptSummary.recorded_count ?? 0}/${receiptSummary.required_count ?? 0}`;
    const executionLabel = executionSummary.states
      ? `done=${executionSummary.states.done ?? 0}, in_progress=${executionSummary.states.in_progress ?? 0}, failed=${executionSummary.states.failed ?? 0}`
      : 'n/a';
    const meta = document.createElement('p');
    meta.className = 'module-manager__item-meta';
    meta.textContent = `release=${release.release_id || 'n/a'} • status=${release.publication_status || 'unknown'} • launchable=${release.launchable ? 'yes' : 'no'} • exec=${executionLabel} • receipts=${receiptLabel} • mapping=${mappingLabel}`;
    li.append(title, meta);
    if (release.release_id) {
      const useRelease = document.createElement('button');
      useRelease.type = 'button';
      useRelease.textContent = 'Use this release below';
      useRelease.addEventListener('click', () => {
        selectRelease(release.release_id || '');
      });
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = 'Validate publication coverage';
      const result = document.createElement('p');
      result.className = 'module-manager__item-meta';
      result.setAttribute('role', 'status');
      const findings = document.createElement('ul');
      button.addEventListener('click', async () => {
        button.disabled = true;
        result.textContent = 'Validating pinned scenes and quest…';
        clearChildren(findings);
        try {
          const coverage = await checkCoverage(release.release_id);
          if (typeof coverage.complete !== 'boolean' || !Array.isArray(coverage.blocking_findings)) {
            throw new Error('Invalid coverage response');
          }
          result.textContent = coverage.complete
            ? `Publication coverage passed: ${coverage.placed_count}/${coverage.scene_count} pinned scenes; quest verified=${coverage.quest_verified ? 'yes' : 'no'}. This does not certify source fidelity.`
            : `Publication blocked: ${coverage.placed_count}/${coverage.scene_count} pinned scenes; quest verified=${coverage.quest_verified ? 'yes' : 'no'}.`;
          renderList(findings, coverage.blocking_findings.map((finding) => findingItem(finding)));
          if (coverage.complete) {
            clearChildren(findings);
          }
        }
        catch (error) {
          result.textContent = `Validation failed: ${error.message || 'request_failed'}`;
          renderList(findings, (error.payload?.error?.findings || []).map((finding) => findingItem(finding)));
        }
        finally {
          button.disabled = false;
        }
      });
      li.append(useRelease, button, result, findings);
    }
    return li;
  }

  async function requestJson(url, options = {}) {
    const response = await fetch(url, {
      credentials: 'same-origin',
      ...options,
    });
    const raw = await response.text();
    let payload = null;
    if (raw !== '') {
      try {
        payload = JSON.parse(raw);
      }
      catch (_error) {
        const parseErr = new Error(`invalid_json_response (HTTP ${response.status})`);
        parseErr.payload = { raw: raw.slice(0, 400) };
        throw parseErr;
      }
    }
    if (!response.ok) {
      const error = payload?.error || {};
      const message = error.code || `HTTP_${response.status}` || response.statusText || 'request_failed';
      const err = new Error(message);
      err.payload = payload;
      throw err;
    }
    if (!payload || typeof payload !== 'object') {
      const emptyErr = new Error('empty_json_response');
      emptyErr.payload = { status: response.status };
      throw emptyErr;
    }
    return payload;
  }

  Drupal.behaviors.moduleManager = {
    attach(context, settings) {
      const [wrapper] = once('module-manager', '[data-module-manager]', context);
      if (!wrapper) {
        return;
      }

      const mmSettings = settings?.dungeoncrawlerContent?.moduleManager || {};
      const status = wrapper.querySelector('[data-module-manager-status]');
      const overviewStatus = wrapper.querySelector('[data-module-manager-overview-status]');
      const overviewList = wrapper.querySelector('[data-module-manager-overview-list]');
      const overviewRefresh = wrapper.querySelector('[data-module-manager-refresh-overview]');
      const catalogList = wrapper.querySelector('[data-module-manager-catalog]');
      const localArchivesList = wrapper.querySelector('[data-module-manager-local-archives]');
      const localArchiveSearch = wrapper.querySelector('[data-module-manager-local-archives-search]');
      const packsList = wrapper.querySelector('[data-module-manager-packs]');
      const findingsList = wrapper.querySelector('[data-module-manager-findings]');
      const releasesList = wrapper.querySelector('[data-module-manager-releases]');
      const readinessSummary = wrapper.querySelector('[data-module-manager-readiness-summary]');
      const readinessScore = wrapper.querySelector('[data-module-manager-readiness-score]');
      const readinessRecommendations = wrapper.querySelector('[data-module-manager-readiness-recommendations]');
      const readinessInventoryTable = wrapper.querySelector('[data-module-manager-readiness-inventory-table]');
      const metrics = wrapper.querySelector('[data-module-manager-metrics]');
      const summaryMetrics = wrapper.querySelector('[data-module-manager-summary]');
      const plan = wrapper.querySelector('[data-module-manager-plan]');
      const gapList = wrapper.querySelector('[data-module-manager-generation-gaps]');
      const gapSummary = wrapper.querySelector('[data-module-manager-generation-summary]');
      const proposalOutput = wrapper.querySelector('[data-module-manager-generation-proposal]');
      const validationRelease = wrapper.querySelector('[data-module-manager-validation-release]');
      const validationProfile = wrapper.querySelector('[data-module-manager-validation-profile]');
      const validationDungeonId = wrapper.querySelector('[data-module-manager-validation-dungeon-id]');
      const validationVersionId = wrapper.querySelector('[data-module-manager-validation-version-id]');
      const runValidation = wrapper.querySelector('[data-module-manager-run-validation]');
      const validationStatus = wrapper.querySelector('[data-module-manager-validation-status]');
      const validationSummary = wrapper.querySelector('[data-module-manager-validation-summary]');
      const validationFindings = wrapper.querySelector('[data-module-manager-validation-findings]');
      const validationReport = wrapper.querySelector('[data-module-manager-validation-report]');
      const actorReviewRelease = wrapper.querySelector('[data-module-manager-actor-review-release]');
      const actorReviewPlanHash = wrapper.querySelector('[data-module-manager-actor-review-plan-hash]');
      const actorReviewResource = wrapper.querySelector('[data-module-manager-actor-review-resource]');
      const actorReviewDecisionStatus = wrapper.querySelector('[data-module-manager-actor-review-status]');
      const actorReviewOrigin = wrapper.querySelector('[data-module-manager-actor-review-origin]');
      const actorReviewRationale = wrapper.querySelector('[data-module-manager-actor-review-rationale]');
      const actorReviewRoomId = wrapper.querySelector('[data-module-manager-actor-review-room-id]');
      const actorReviewRoomVersion = wrapper.querySelector('[data-module-manager-actor-review-room-version]');
      const actorReviewInstance = wrapper.querySelector('[data-module-manager-actor-review-instance]');
      const actorReviewAnchor = wrapper.querySelector('[data-module-manager-actor-review-anchor]');
      const actorReviewEvidence = wrapper.querySelector('[data-module-manager-actor-review-evidence]');
      const actorReviewSave = wrapper.querySelector('[data-module-manager-save-actor-review]');
      const actorReviewStatus = wrapper.querySelector('[data-module-manager-actor-review-feedback]');
      const reviewedDefinition = wrapper.querySelector('[data-module-manager-reviewed-definition]');
      const reviewConfirmed = wrapper.querySelector('[data-module-manager-review-confirmed]');
      const saveDefinition = wrapper.querySelector('[data-module-manager-save-definition]');
      const bindDefinition = wrapper.querySelector('[data-module-manager-bind-definition]');
      const bindRelease = wrapper.querySelector('[data-module-manager-bind-release]');
      const definitionStatus = wrapper.querySelector('[data-module-manager-definition-status]');
      const form = wrapper.querySelector('[data-module-manager-inspect-form]');
      const archivePathField = wrapper.querySelector('#module-manager-archive-path');
      const archiveHashField = wrapper.querySelector('#module-manager-archive-hash');
      const inspectSubmit = wrapper.querySelector('[data-module-manager-inspect-submit]');
      let activeProposal = null;
      let activePlanHash = null;
      let savedDefinition = null;
      let localArchives = [];
      let overviewByArchiveName = new Map();

      const setStatus = (message) => {
        if (status) status.textContent = message;
      };

      const inspectModuleFromEntry = (entry) => {
        if (archivePathField) {
          archivePathField.value = entry.archive_path || '';
        }
        if (archiveHashField) {
          archiveHashField.value = entry.sha256 || '';
        }
        if (inspectSubmit) {
          inspectSubmit.click();
        }
      };

      const renderLocalArchives = () => {
        if (!localArchivesList) {
          return;
        }
        const term = (localArchiveSearch?.value || '').trim().toLowerCase();
        const filtered = localArchives.filter((archive) => !term || archive.name.toLowerCase().includes(term));
        const moduleExplorerPageUrl = mmSettings?.urls?.moduleExplorerPage || '/admin/content/dungeoncrawler/module-explorer';
        renderList(localArchivesList, filtered.map((archive) => {
          const li = document.createElement('li');
          li.className = 'module-manager__item';
          const title = document.createElement('p');
          title.className = 'module-manager__item-title';
          title.textContent = archive.name;
          const meta = document.createElement('p');
          meta.className = 'module-manager__item-meta';
          const hashLabel = archive.sha256 ? archive.sha256.slice(0, 16) : 'n/a';
          meta.textContent = `${formatBytes(archive.bytes)} • sha256=${hashLabel}…`;
          const overview = overviewByArchiveName.get(archive.name);
          if (overview?.analysis) {
            const score = document.createElement('p');
            score.className = 'module-manager__item-meta module-manager__overview-score';
            score.dataset.state = String(overview.analysis.state || 'unknown');
            score.textContent = `Score ${overview.analysis.score ?? 'n/a'}/100 • Grade ${overview.analysis.grade || 'n/a'} • ${overview.analysis.state || 'unknown'}`;
            li.append(title, score, meta);
          }
          else if (overview?.error?.code) {
            const score = document.createElement('p');
            score.className = 'module-manager__item-meta';
            score.textContent = `Scoring unavailable: ${overview.error.code}`;
            li.append(title, score, meta);
          }
          else {
            li.append(title, meta);
          }
          const inspectButton = document.createElement('button');
          inspectButton.type = 'button';
          inspectButton.textContent = 'Inspect + score module';
          inspectButton.addEventListener('click', () => inspectModuleFromEntry(archive));
          const explorerLink = document.createElement('a');
          explorerLink.href = moduleExplorerPageUrl;
          explorerLink.textContent = 'Open Module Explorer';
          li.append(inspectButton, document.createTextNode(' '), explorerLink);
          return li;
        }));
      };

      const loadLocalArchives = async () => {
        if (!localArchivesList || !mmSettings?.urls?.localArchives) {
          return;
        }
        const result = await requestJson(mmSettings.urls.localArchives);
        const archives = result?.data?.archives;
        if (!Array.isArray(archives)) {
          throw new Error('local_archives_invalid');
        }
        localArchives = archives
          .filter((archive) => archive && typeof archive.name === 'string' && archive.name.endsWith('.zip'))
          .sort((a, b) => String(a.name).localeCompare(String(b.name)));
        renderLocalArchives();
      };

      const loadOverview = async () => {
        if (!overviewList || !overviewStatus || !mmSettings?.urls?.localArchiveOverview) {
          return;
        }
        overviewStatus.textContent = 'Scoring local modules…';
        const result = await requestJson(mmSettings.urls.localArchiveOverview);
        const rows = result?.data?.overview;
        if (!Array.isArray(rows)) {
          throw new Error('module_overview_invalid');
        }
        overviewByArchiveName = new Map(rows.map((row) => [String(row.name || ''), row]));
        renderList(overviewList, rows.map((row) => moduleOverviewItem(row, inspectModuleFromEntry)));
        renderLocalArchives();
        overviewStatus.textContent = `Scored ${rows.length} local modules.`;
      };

      const loadCatalog = async () => {
        setStatus('Loading catalog…');
        const result = await requestJson(mmSettings.urls.catalog);
        const references = result?.data?.references || [];
        const items = references.map((reference) => catalogItem(reference));
        renderList(catalogList, items);
        setStatus(`Catalog loaded (${references.length} references).`);
      };

      const loadReleases = async () => {
        const result = await requestJson(mmSettings.urls.releases);
        const releases = result?.data?.releases || [];
        const checkCoverage = async (releaseId) => {
          const url = mmSettings.urls.releaseCoverage.replace('__release_id__', encodeURIComponent(releaseId));
          return (await requestJson(url)).data;
        };
        const selectRelease = (releaseId) => {
          bindRelease.value = releaseId;
          if (validationRelease) {
            validationRelease.value = releaseId;
          }
          if (actorReviewRelease) {
            actorReviewRelease.value = releaseId;
          }
          if (actorReviewPlanHash && activePlanHash) {
            actorReviewPlanHash.value = activePlanHash;
          }
        };
        renderList(releasesList, releases.map((release) => releaseItem(release, checkCoverage, selectRelease)));
      };

      const submitInspect = async (event) => {
        event.preventDefault();
        setStatus('Inspecting archive…');
        renderList(findingsList, []);
        renderList(gapList, []);
        proposalOutput.textContent = '{}';
        reviewedDefinition.value = '';
        reviewConfirmed.checked = false;
        activeProposal = null;
        savedDefinition = null;
        activePlanHash = null;
        definitionStatus.textContent = '';
        if (readinessSummary) {
          readinessSummary.textContent = 'Run Inspect archive to calculate module state, readiness score, and import risk profile.';
        }
        if (readinessScore) {
          readinessScore.textContent = '';
        }
        if (readinessRecommendations) {
          clearChildren(readinessRecommendations);
        }
        if (readinessInventoryTable) {
          clearChildren(readinessInventoryTable);
        }

        const formData = new FormData(form);
        const archivePath = String(formData.get('archive_path') || '').trim();
        const expectedHash = String(formData.get('expected_sha256') || '').trim();
        const expectedPlanHash = String(formData.get('expected_plan_hash') || '').trim();
        const decisionsRaw = String(formData.get('decisions_json') || '').trim();
        const registerRelease = String(formData.get('register_release') || '') === '1';
        const conversionRevision = String(formData.get('conversion_revision') || '').trim();
        if (!archivePath) {
          setStatus('Archive path is required.');
          return;
        }

        const payload = {
          archive_path: archivePath,
        };
        if (expectedHash) {
          payload.expected_sha256 = expectedHash;
        }
        if (expectedPlanHash) {
          payload.expected_plan_hash = expectedPlanHash;
        }
        if (registerRelease) {
          if (!expectedHash) {
            setStatus('Expected SHA-256 is required when registering a release.');
            return;
          }
          if (!expectedPlanHash) {
            setStatus('Expected plan hash is required when registering a release.');
            return;
          }
          payload.register_release = true;
          if (conversionRevision) {
            payload.release = { conversion_revision: conversionRevision };
          }
        }
        if (decisionsRaw) {
          try {
            const parsed = JSON.parse(decisionsRaw);
            if (!parsed || Array.isArray(parsed) || typeof parsed !== 'object') {
              setStatus('Decisions JSON must be an object.');
              return;
            }
            payload.decisions = parsed;
          }
          catch (_error) {
            setStatus('Decisions JSON is invalid.');
            return;
          }
        }

        try {
          const result = await requestJson(mmSettings.urls.inspect, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': mmSettings.csrfToken || '',
            },
            body: JSON.stringify(payload),
          });
          const inventory = result?.data?.inventory || {};
          const dryRunPlan = result?.data?.plan || {};
          activePlanHash = dryRunPlan.plan_hash;
          renderList(packsList, (inventory.packs || []).map((pack) => packItem(pack)));
          renderList(findingsList, (inventory.findings || []).map((finding) => findingItem(finding)));
          const release = result?.data?.release || null;
          const analysis = result?.data?.module_state_analysis || {};
          const summary = inventory.resource_summary || {};
          const refSummary = summary.references || {};
          const releaseLabel = release ? ` • release: ${release.release_id || 'n/a'} (${release.publication_status || 'unknown'})` : '';
          metrics.textContent = `Plan hash: ${dryRunPlan.plan_hash || 'n/a'} • publishable: ${dryRunPlan.is_publishable ? 'yes' : 'no'} • resources: ${summary.total ?? 0} • refs(uuid/comp/module): ${(refSummary.uuid ?? 0)}/${(refSummary.compendium ?? 0)}/${(refSummary.modulesPath ?? 0)}${releaseLabel}`;
          const byType = summary.by_type || {};
          const byDisposition = summary.by_disposition || {};
          const byCollection = summary.by_source_collection || {};
          summaryMetrics.textContent = `Forseti inventory: dungeons=${analysis?.counts?.dungeons ?? 0}, rooms=${analysis?.counts?.rooms ?? 0}, actors=${analysis?.counts?.actors ?? 0}, items=${analysis?.counts?.items ?? 0}, journal_quest_items=${analysis?.counts?.journal_quest_items ?? 0} • Source collections: adventure.cards=${byCollection['adventure.cards'] ?? 0}, adventure.combats=${byCollection['adventure.combats'] ?? 0}, adventure.macros=${byCollection['adventure.macros'] ?? 0}, adventure.playlists=${byCollection['adventure.playlists'] ?? 0} • Dispositions: requires_authoring=${byDisposition.requires_authoring ?? 0}, reference_only=${byDisposition.reference_only ?? 0}, unsupported=${byDisposition.unsupported ?? 0}`;
          plan.textContent = JSON.stringify(dryRunPlan, null, 2);
          if (readinessSummary && readinessScore && readinessRecommendations) {
            const counts = analysis.counts || {};
            const signals = analysis.signals || {};
            readinessSummary.textContent = analysis.summary || 'No module state analysis returned.';
            readinessScore.textContent = `Score ${analysis.score ?? 'n/a'}/100 (grade ${analysis.grade || 'n/a'}) • state=${analysis.state || 'unknown'} • publishable=${analysis.publishable ? 'yes' : 'no'} • dungeons=${counts.dungeons ?? 0} • rooms=${counts.rooms ?? 0} • actors=${counts.actors ?? 0} • items=${counts.items ?? 0} • journal/quest=${counts.journal_quest_items ?? 0} • authoring pressure=${signals.authoring_pressure_pct ?? 0}%`;
            readinessScore.dataset.state = String(analysis.state || 'unknown');
            readinessScore.dataset.grade = String(analysis.grade || 'n/a');
            renderList(readinessRecommendations, (analysis.recommendations || []).map((text) => recommendationItem(text)));
            if (readinessInventoryTable) {
              clearChildren(readinessInventoryTable);
              const rows = analysis?.object_inventory?.rows;
              if (Array.isArray(rows) && rows.length) {
                readinessInventoryTable.appendChild(createInventoryTable(rows));
              }
            }
          }
          const gaps = result?.data?.generation_gaps || {};
          const inspectedHash = inventory.archive?.sha256;
          const paths = gaps.by_generation_path || {};
          gapSummary.textContent = `${gaps.count ?? 0} resources with source-evidence gaps; ${gaps.authoring_review_count ?? 0} require authoring review • ${Object.entries(paths).map(([path, count]) => `${path}=${count}`).join(', ') || 'none'}. Showing up to five per generation path and review status.`;
          renderList(gapList, (gaps.sample || []).map((gap) => {
            const li = document.createElement('li');
            li.className = 'module-manager__item';
            const label = document.createElement('p');
            label.className = 'module-manager__item-meta';
            const reviewLabel = gap.requires_review ? 'authoring review required'
              : gap.source_disposition === 'reference_only' ? 'reference-only evidence gap' : 'unsupported source evidence gap';
            label.textContent = `${gap.resource_type}: ${gap.resource_id} • ${reviewLabel} • missing source: ${gap.missing_source_fields.join(', ') || 'none'} • unresolved: ${gap.unresolved_gaps.join(', ')} • path: ${gap.generation_path}${gap.suggested_original_size_category ? ` • proposed original size: ${gap.suggested_original_size_category} (review required; not source evidence)` : ''}`;
            li.appendChild(label);
            if (gap.proposal_available) {
              const button = document.createElement('button');
              button.type = 'button';
              button.textContent = 'Generate original proposal (review required)';
              button.addEventListener('click', async () => {
                button.disabled = true;
                proposalOutput.textContent = 'Generating unpublished definition proposal…';
                try {
                  const generation = await requestJson(mmSettings.urls.generateDefinition, {
                    method: 'POST',
                    headers: {
                      'Content-Type': 'application/json',
                      'X-CSRF-Token': mmSettings.csrfToken || '',
                    },
                    body: JSON.stringify({
                      archive_path: archivePath,
                      expected_sha256: inspectedHash,
                      expected_plan_hash: dryRunPlan.plan_hash,
                      resource_id: gap.resource_id,
                      decisions: payload.decisions || {},
                    }),
                  });
                  proposalOutput.textContent = JSON.stringify(generation.data, null, 2);
                  activeProposal = generation.data;
                  savedDefinition = null;
                  reviewConfirmed.checked = false;
                  reviewedDefinition.value = JSON.stringify(generation.data.proposal.payload, null, 2);
                  definitionStatus.textContent = 'Edit and review the original proposal before saving; it is not yet a canonical definition.';
                }
                catch (error) {
                  proposalOutput.textContent = `Proposal failed: ${error.message || 'request_failed'}`;
                }
                finally {
                  button.disabled = false;
                }
              });
              li.appendChild(button);
            }
            return li;
          }));
          setStatus(`Inspection complete (${(inventory.resources || []).length} resources).`);
          if (release) {
            bindRelease.value = release.release_id || '';
            if (validationRelease) {
              validationRelease.value = release.release_id || '';
            }
            if (actorReviewRelease) {
              actorReviewRelease.value = release.release_id || '';
            }
            if (actorReviewPlanHash) {
              actorReviewPlanHash.value = activePlanHash || '';
            }
            await loadReleases();
          }
        }
        catch (error) {
          const findings = error?.payload?.error?.findings || [];
          renderList(findingsList, findings.map((finding) => findingItem(finding)));
          plan.textContent = '{}';
          metrics.textContent = '';
          summaryMetrics.textContent = '';
          gapSummary.textContent = '';
          renderList(gapList, []);
          if (readinessSummary) {
            readinessSummary.textContent = 'Module state analysis unavailable due to inspection failure.';
          }
          if (readinessScore) {
            readinessScore.textContent = '';
            readinessScore.dataset.state = 'unknown';
            readinessScore.dataset.grade = 'n/a';
          }
          if (readinessRecommendations) {
            clearChildren(readinessRecommendations);
          }
          if (readinessInventoryTable) {
            clearChildren(readinessInventoryTable);
          }
          proposalOutput.textContent = '{}';
          activeProposal = null;
          savedDefinition = null;
          activePlanHash = null;
          reviewedDefinition.value = '';
          setStatus(`Inspection failed: ${error.message || 'request_failed'}`);
        }
      };

      renderList(catalogList, []);
      renderList(packsList, []);
      renderList(findingsList, []);
      renderList(releasesList, []);
      renderList(gapList, []);
      if (readinessRecommendations) {
        clearChildren(readinessRecommendations);
      }
      if (readinessInventoryTable) {
        clearChildren(readinessInventoryTable);
      }
      if (summaryMetrics) {
        summaryMetrics.textContent = '';
      }
      if (form) {
        form.addEventListener('submit', submitInspect);
      }
      if (overviewRefresh) {
        overviewRefresh.addEventListener('click', () => {
          loadOverview().catch((error) => {
            if (overviewStatus) {
              overviewStatus.textContent = `Module overview failed: ${error.message || 'request_failed'}`;
            }
          });
        });
      }
      if (localArchiveSearch) {
        localArchiveSearch.addEventListener('input', renderLocalArchives);
      }
      reviewedDefinition.addEventListener('input', () => {
        savedDefinition = null;
      });
      saveDefinition.addEventListener('click', async () => {
        if (!activeProposal || !reviewConfirmed.checked || !activePlanHash) {
          definitionStatus.textContent = 'Generate a proposal and explicitly confirm original-content review before saving.';
          return;
        }
        let payload;
        try {
          payload = JSON.parse(reviewedDefinition.value);
          if (!payload || Array.isArray(payload) || typeof payload !== 'object') {
            throw new Error('JSON object required');
          }
        }
        catch (error) {
          definitionStatus.textContent = `Definition JSON is invalid: ${error.message}`;
          return;
        }
        saveDefinition.disabled = true;
        definitionStatus.textContent = 'Saving reviewed definition through the canonical editor…';
        try {
          const url = mmSettings.urls.createDefinition.replace('__family__', encodeURIComponent(activeProposal.proposal.family));
          const result = await requestJson(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': mmSettings.csrfToken || '',
            },
            body: JSON.stringify({ payload }),
          });
          savedDefinition = result.data;
          definitionStatus.textContent = `Saved ${savedDefinition.family} ${savedDefinition.definition_id}@${savedDefinition.version}. Bind it to the matching release only after reviewing its intended placement.`;
        }
        catch (error) {
          definitionStatus.textContent = `Definition save failed: ${error.message || 'request_failed'}`;
        }
        finally {
          saveDefinition.disabled = false;
        }
      });
      bindDefinition.addEventListener('click', async () => {
        const releaseId = bindRelease.value.trim();
        if (!savedDefinition || !activeProposal || !reviewConfirmed.checked || !activePlanHash || !releaseId) {
          definitionStatus.textContent = 'Save and review a definition, then provide its matching unpublished release ID.';
          return;
        }
        bindDefinition.disabled = true;
        definitionStatus.textContent = 'Binding reviewed canonical version and recording its native receipt…';
        try {
          const url = mmSettings.urls.bindDefinition.replace('__release_id__', encodeURIComponent(releaseId));
          await requestJson(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': mmSettings.csrfToken || '',
            },
            body: JSON.stringify({
              resource_id: activeProposal.resource_id,
              expected_plan_hash: activePlanHash,
              family: savedDefinition.family,
              definition_id: savedDefinition.definition_id,
              version: savedDefinition.version,
              rights_status: 'project_original',
              review_confirmed: true,
            }),
          });
          definitionStatus.textContent = `Reviewed definition bound to ${releaseId}; native receipt recorded. Publication still requires every release gate.`;
          await loadReleases();
        }
        catch (error) {
          definitionStatus.textContent = `Definition binding failed: ${error.message || 'request_failed'}`;
        }
        finally {
          bindDefinition.disabled = false;
        }
      });
      runValidation.addEventListener('click', async () => {
        const releaseId = validationRelease.value.trim();
        if (!releaseId) {
          validationStatus.textContent = 'Release ID is required for validation.';
          return;
        }
        const profile = String(validationProfile.value || 'original_adaptation').trim();
        const dungeonId = String(validationDungeonId.value || '').trim();
        const versionId = String(validationVersionId.value || '').trim();
        runValidation.disabled = true;
        validationStatus.textContent = 'Running full conversion validation…';
        validationSummary.textContent = '';
        renderList(validationFindings, []);
        try {
          const url = new URL(mmSettings.urls.releaseValidation.replace('__release_id__', encodeURIComponent(releaseId)), window.location.origin);
          if (profile) {
            url.searchParams.set('profile', profile);
          }
          if (dungeonId && versionId) {
            url.searchParams.set('dungeon_id', dungeonId);
            url.searchParams.set('version_id', versionId);
          }
          const report = (await requestJson(url.toString())).data;
          validationReport.textContent = JSON.stringify(report, null, 2);
          const required = report.required_resources || {};
          const actorCoverage = report.actor_coverage || {};
          const queue = Array.isArray(report.remediation_queue) ? report.remediation_queue : [];
          validationSummary.textContent = `complete=${report.complete ? 'yes' : 'no'} • profile=${report.profile || 'n/a'} • required receipts=${required.with_receipts ?? 0}/${required.total ?? 0} • actor reviewed=${actorCoverage.reviewed_decisions ?? 0}/${actorCoverage.source_total ?? 0} • actor placed=${actorCoverage.placed_in_pinned_rooms ?? 0} • remediation=${queue.length}`;
          const findings = report.blocking_findings || report.all_findings || [];
          renderList(validationFindings, findings.map((finding) => findingItem(finding)));
          validationStatus.textContent = report.complete
            ? 'Conversion validation passed for selected profile.'
            : 'Conversion validation reported blocking findings.';
        }
        catch (error) {
          validationStatus.textContent = `Validation failed: ${error.message || 'request_failed'}`;
          validationReport.textContent = JSON.stringify(error?.payload || {}, null, 2);
          renderList(validationFindings, (error.payload?.error?.findings || []).map((finding) => findingItem(finding)));
        }
        finally {
          runValidation.disabled = false;
        }
      });
      actorReviewSave.addEventListener('click', async () => {
        const releaseId = actorReviewRelease.value.trim();
        const planHash = actorReviewPlanHash.value.trim();
        const resourceId = actorReviewResource.value.trim();
        const statusValue = String(actorReviewDecisionStatus.value || '').trim();
        const originValue = String(actorReviewOrigin.value || '').trim();
        const rationale = actorReviewRationale.value.trim();
        if (!releaseId || !planHash || !resourceId || !statusValue || !originValue || !rationale) {
          actorReviewStatus.textContent = 'Release ID, plan hash, resource ID, status, origin, and rationale are required.';
          return;
        }
        let sourceEvidence = {};
        if (actorReviewEvidence.value.trim()) {
          try {
            sourceEvidence = JSON.parse(actorReviewEvidence.value);
            if (!sourceEvidence || Array.isArray(sourceEvidence) || typeof sourceEvidence !== 'object') {
              throw new Error('must be a JSON object');
            }
          }
          catch (error) {
            actorReviewStatus.textContent = `Source evidence JSON is invalid: ${error.message}`;
            return;
          }
        }
        const payload = {
          resource_id: resourceId,
          expected_plan_hash: planHash,
          status: statusValue,
          origin: originValue,
          rationale,
          source_evidence: sourceEvidence,
        };
        if (statusValue === 'placed') {
          const roomId = actorReviewRoomId.value.trim();
          const roomVersion = actorReviewRoomVersion.value.trim();
          const instanceId = actorReviewInstance.value.trim();
          const anchorHex = actorReviewAnchor.value.trim();
          const coordinates = /^(-?\d+)\s*,\s*(-?\d+)$/.exec(anchorHex);
          if (!roomId || !roomVersion || !instanceId || !coordinates
            || !Number.isSafeInteger(Number(coordinates[1])) || !Number.isSafeInteger(Number(coordinates[2]))) {
            actorReviewStatus.textContent = 'room_id, room_version, instance_id, and integer q,r anchor are required when status is placed.';
            return;
          }
          payload.placement = {
            room_id: roomId,
            room_version: roomVersion,
            instance_id: instanceId,
            anchor_hex: { q: Number(coordinates[1]), r: Number(coordinates[2]) },
          };
        }
        actorReviewSave.disabled = true;
        actorReviewStatus.textContent = 'Saving actor placement review…';
        try {
          const url = mmSettings.urls.actorPlacementReview.replace('__release_id__', encodeURIComponent(releaseId));
          await requestJson(url, {
            method: 'POST',
            headers: {
              'Content-Type': 'application/json',
              'X-CSRF-Token': mmSettings.csrfToken || '',
            },
            body: JSON.stringify(payload),
          });
          actorReviewStatus.textContent = 'Actor placement review saved.';
          await loadReleases();
        }
        catch (error) {
          actorReviewStatus.textContent = `Actor review save failed: ${error.message || 'request_failed'}`;
        }
        finally {
          actorReviewSave.disabled = false;
        }
      });
      Promise.allSettled([loadCatalog(), loadReleases(), loadLocalArchives(), loadOverview()]).then((results) => {
        const failed = results.find((result) => result.status === 'rejected');
        if (failed && failed.reason) {
          const message = failed.reason.message || 'request_failed';
          setStatus(`Module manager partially loaded: ${message}`);
          if (overviewStatus && !overviewByArchiveName.size) {
            overviewStatus.textContent = `Module overview failed: ${message}`;
          }
        }
      });
    },
  };
})(Drupal, drupalSettings, once);
