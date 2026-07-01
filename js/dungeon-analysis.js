(function (Drupal, drupalSettings, once) {
  let mermaidInitialized = false;
  let renderCounter = 0;

  function escapeLabel(value) {
    return String(value || '')
      .replace(/\\/g, '\\\\')
      .replace(/"/g, '\\"')
      .replace(/\n/g, ' ');
  }

  function readColor(styles, propertyName, fallback) {
    const value = styles && styles.getPropertyValue ? styles.getPropertyValue(propertyName).trim() : '';
    return value || fallback;
  }

  function buildMermaidTheme(root) {
    const styles = root ? window.getComputedStyle(root) : null;
    const surface = readColor(styles, '--analysis-surface', '#ffffff');
    const surfaceMuted = readColor(styles, '--analysis-surface-muted', '#f5f5f5');
    const surfaceDeep = readColor(styles, '--analysis-surface-deep', '#e9ecef');
    const border = readColor(styles, '--analysis-border', '#c6ccd2');
    const text = readColor(styles, '--analysis-text', '#1f2933');
    const textMuted = readColor(styles, '--analysis-text-muted', '#52606d');

    return {
      startOnLoad: false,
      securityLevel: 'strict',
      theme: 'base',
      themeVariables: {
        background: surface,
        mainBkg: surface,
        secondBkg: surfaceMuted,
        tertiaryBkg: surfaceDeep,
        primaryColor: surfaceMuted,
        secondaryColor: surface,
        tertiaryColor: surfaceDeep,
        primaryBorderColor: border,
        secondaryBorderColor: border,
        tertiaryBorderColor: border,
        primaryTextColor: text,
        secondaryTextColor: text,
        tertiaryTextColor: text,
        textColor: text,
        nodeTextColor: text,
        lineColor: textMuted,
        defaultLinkColor: textMuted,
        edgeLabelBackground: surface,
        clusterBkg: surfaceMuted,
        clusterBorder: border,
        titleColor: text,
      },
      flowchart: {
        useMaxWidth: true,
        htmlLabels: false,
        curve: 'basis',
      },
    };
  }

  function buildMermaidSource(graph) {
    const nodes = graph && Array.isArray(graph.nodes) ? graph.nodes : [];
    const edges = graph && Array.isArray(graph.edges) ? graph.edges : [];
    const idMap = new Map();
    const lines = ['flowchart TD'];

    nodes.forEach((node, index) => {
      const roomId = String((node && node.room_id) || '').trim();
      if (!roomId) {
        return;
      }
      const alias = `R${index + 1}`;
      idMap.set(roomId, alias);
      const label = escapeLabel(String((node && node.label) || roomId));
      const roomIdLabel = escapeLabel(roomId);
      lines.push(`  ${alias}["${label}\\n(${roomIdLabel})"]`);
    });

    edges.forEach((edge) => {
      const fromRoomId = String((edge && edge.from_room_id) || '').trim();
      const toRoomId = String((edge && edge.to_room_id) || '').trim();
      if (!fromRoomId || !toRoomId) {
        return;
      }
      const from = idMap.get(fromRoomId);
      const to = idMap.get(toRoomId);
      if (!from || !to) {
        return;
      }
      lines.push(`  ${from} --> ${to}`);
    });

    return lines.join('\n');
  }

  async function renderMermaidGraph(container, source) {
    if (!window.mermaid) {
      throw new Error('Mermaid library failed to load.');
    }
    if (!mermaidInitialized) {
      window.mermaid.initialize(buildMermaidTheme(container.closest('.dc-dungeon-analysis')));
      mermaidInitialized = true;
    }

    const renderId = `dc-dungeon-analysis-${++renderCounter}`;
    const result = await window.mermaid.render(renderId, source);
    container.innerHTML = result.svg;
  }

  function setStatus(statusEl, message, isError = false) {
    if (!statusEl) {
      return;
    }
    statusEl.textContent = message;
    statusEl.classList.toggle('dc-dungeon-analysis__status--error', isError);
  }

  function appendDebug(debugEl, message) {
    if (!debugEl) {
      return;
    }
    const line = `[${new Date().toISOString()}] ${message}`;
    debugEl.textContent = debugEl.textContent
      ? `${debugEl.textContent}\n${line}`
      : line;
    debugEl.scrollTop = debugEl.scrollHeight;
  }

  Drupal.behaviors.dcDungeonAnalysis = {
    attach(context) {
      const root = once('dc-dungeon-analysis', '.dc-dungeon-analysis', context)[0];
      if (!root) {
        console.debug('[DungeonAnalysis] attach skipped: no root');
        return;
      }
      console.debug('[DungeonAnalysis] attach start', root);

      const settings = drupalSettings
        && drupalSettings.dungeoncrawler_content
        && drupalSettings.dungeoncrawler_content.dungeonAnalysis
        ? drupalSettings.dungeoncrawler_content.dungeonAnalysis
        : {};
      const dungeons = Array.isArray(settings.dungeons) ? settings.dungeons : [];
      const defaultDungeonId = String(settings.defaultDungeonId || '').trim();
      const apiUrlPattern = String(settings.apiUrlPattern || '').trim();
      const selectEl = root.querySelector('#dc-dungeon-analysis-select');
      const statusEl = root.querySelector('#dc-dungeon-analysis-status');
      const diagramEl = root.querySelector('#dc-dungeon-analysis-diagram');
      const debugEl = root.querySelector('#dc-dungeon-analysis-debug');

      appendDebug(debugEl, `attach: options=${dungeons.length} default=${defaultDungeonId || '<none>'} api=${apiUrlPattern || '<missing>'}`);
      console.debug('[DungeonAnalysis] settings', { defaultDungeonId, apiUrlPattern, dungeonCount: dungeons.length });

      if (!selectEl || !diagramEl) {
        setStatus(statusEl, 'Dungeon analysis page contract violation: required UI elements are missing.', true);
        console.error('[DungeonAnalysis] missing required elements', { hasSelect: !!selectEl, hasDiagram: !!diagramEl });
        appendDebug(debugEl, `error: missing required elements select=${!!selectEl} diagram=${!!diagramEl}`);
        return;
      }

      if (!apiUrlPattern || !dungeons.length) {
        setStatus(statusEl, 'No canonical dungeons are available.', true);
        appendDebug(debugEl, 'error: no dungeons or missing api URL pattern');
        console.warn('[DungeonAnalysis] no dungeons or api url pattern', { apiUrlPattern, dungeonCount: dungeons.length });
        return;
      }
      selectEl.innerHTML = '';
      console.groupCollapsed('[DungeonAnalysis] populate dropdown');
      dungeons.forEach((dungeon) => {
        const dungeonId = String((dungeon && dungeon.dungeon_id) || '').trim();
        if (!dungeonId) {
          console.warn('[DungeonAnalysis] skipping dungeon with empty id', dungeon);
          return;
        }
        const label = String((dungeon && dungeon.name) || dungeonId);
        const parsedRoomCount = Number((dungeon && dungeon.room_count) || 0);
        const parsedEdgeCount = Number((dungeon && dungeon.edge_count) || 0);
        const edgeSourceLabel = String((dungeon && dungeon.edge_source_label) || (dungeon && dungeon.edge_source) || 'unknown');
        const roomCount = Number.isFinite(parsedRoomCount) ? parsedRoomCount : 0;
        const edgeCount = Number.isFinite(parsedEdgeCount) ? parsedEdgeCount : 0;
        const option = document.createElement('option');
        option.value = dungeonId;
        option.textContent = `${label} (${dungeonId}) — ${roomCount} rooms / ${edgeCount} exits [${edgeSourceLabel}]`;
        if (dungeonId === defaultDungeonId) {
          option.selected = true;
        }
        selectEl.appendChild(option);
        console.debug('[DungeonAnalysis] option added', { dungeonId, label, roomCount, edgeCount });
      });
      console.groupEnd();
      appendDebug(debugEl, `dropdown populated: ${selectEl.options.length} options`);
      console.debug('[DungeonAnalysis] dropdown populated', { optionCount: selectEl.options.length });

      const loadGraph = async (dungeonId) => {
        const selected = String(dungeonId || '').trim();
        if (!selected) {
          setStatus(statusEl, 'Select a canonical dungeon.', true);
          appendDebug(debugEl, 'error: empty selection');
          return;
        }

        setStatus(statusEl, `Loading ${selected}...`);
        diagramEl.innerHTML = '';
        appendDebug(debugEl, `loadGraph: ${selected}`);
        console.info('[DungeonAnalysis] loadGraph start', { selected });

        let timeoutId = 0;
        try {
          const url = apiUrlPattern.replace('__DUNGEON_ID__', encodeURIComponent(selected));
          console.debug('[DungeonAnalysis] fetch graph', { url });
          const abortController = new AbortController();
          timeoutId = window.setTimeout(() => abortController.abort(), 20000);
          const response = await fetch(url, {
            method: 'GET',
            headers: {
              Accept: 'application/json',
              'X-Requested-With': 'XMLHttpRequest',
            },
            credentials: 'same-origin',
            signal: abortController.signal,
          });
          appendDebug(debugEl, `response: ${response.status} ${response.statusText}`);
          const payload = await response.json().catch(() => ({}));
          if (!response.ok || !payload || !payload.success || !payload.graph) {
            console.error('[DungeonAnalysis] graph fetch failed', { status: response.status, payload });
            appendDebug(debugEl, `error: fetch failed status=${response.status}`);
            throw new Error(String((payload && payload.error) || 'Unable to load dungeon graph.'));
          }

          const source = buildMermaidSource(payload.graph);
          appendDebug(debugEl, `graph payload: nodes=${Array.isArray(payload.graph.nodes) ? payload.graph.nodes.length : 0} edges=${Array.isArray(payload.graph.edges) ? payload.graph.edges.length : 0}`);
          appendDebug(debugEl, `mermaid source:\n${source}`);
          console.debug('[DungeonAnalysis] mermaid source', source);
          await renderMermaidGraph(diagramEl, source);
          const nodeCount = Array.isArray(payload.graph.nodes) ? payload.graph.nodes.length : 0;
          const edgeCount = Array.isArray(payload.graph.edges) ? payload.graph.edges.length : 0;
          const edgeSource = String((payload.graph && payload.graph.edge_source) || '').trim();
          const dungeonName = payload && payload.dungeon ? payload.dungeon.name : selected;
          if (nodeCount > 0 && edgeCount === 0) {
            setStatus(statusEl, `${dungeonName || selected}: ${nodeCount} rooms, 0 exits [${edgeSource || 'unknown source'}].`, true);
          } else {
            setStatus(statusEl, `${dungeonName || selected}: ${nodeCount} rooms, ${edgeCount} exits [${edgeSource || 'unknown source'}].`);
          }
          console.info('[DungeonAnalysis] loadGraph success', { selected, nodeCount, edgeCount, dungeonName });
        } catch (error) {
          setStatus(statusEl, String(error?.message || 'Failed to render dungeon graph.'), true);
          appendDebug(debugEl, `error: ${String(error?.message || error)}`);
          console.error('[DungeonAnalysis] loadGraph error', error);
        } finally {
          if (timeoutId) {
            window.clearTimeout(timeoutId);
          }
        }
      };

      selectEl.addEventListener('change', () => {
        console.debug('[DungeonAnalysis] selection changed', { value: selectEl.value });
        appendDebug(debugEl, `selection changed: ${selectEl.value}`);
        void loadGraph(selectEl.value);
      });
      const initialId = String(selectEl.value || defaultDungeonId || '').trim();
      if (initialId) {
        console.debug('[DungeonAnalysis] initial load', { initialId });
        appendDebug(debugEl, `initial load: ${initialId}`);
        void loadGraph(initialId);
      } else {
        console.warn('[DungeonAnalysis] no initial selection');
        appendDebug(debugEl, 'warning: no initial selection');
      }
    },
  };
})(Drupal, drupalSettings, once);
