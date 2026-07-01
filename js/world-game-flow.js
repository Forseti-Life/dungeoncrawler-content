(function (Drupal, once) {
  let mermaidInitialized = false;
  let mermaidRenderCounter = 0;

  function readColor(styles, propertyName, fallback) {
    const value = styles?.getPropertyValue(propertyName)?.trim();
    return value || fallback;
  }

  function buildMermaidTheme(root) {
    const styles = root ? window.getComputedStyle(root) : null;
    const surface = readColor(styles, '--dc-flow-surface', '#ffffff');
    const surfaceMuted = readColor(styles, '--dc-flow-surface-muted', '#f5f5f5');
    const surfaceDeep = readColor(styles, '--dc-flow-surface-deep', '#e9ecef');
    const border = readColor(styles, '--dc-flow-border', '#c6ccd2');
    const text = readColor(styles, '--dc-flow-text', '#1f2933');
    const textMuted = readColor(styles, '--dc-flow-text-muted', '#52606d');

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
        actorBkg: surface,
        actorBorder: border,
        actorTextColor: text,
        labelBoxBkgColor: surface,
        labelBoxBorderColor: border,
        labelTextColor: text,
        loopTextColor: text,
        noteBkgColor: surfaceMuted,
        noteBorderColor: border,
        noteTextColor: text,
        signalColor: text,
        signalTextColor: text,
        sequenceNumberColor: text,
        activationBorderColor: border,
        activationBkgColor: surfaceDeep,
      },
      flowchart: {
        useMaxWidth: true,
        htmlLabels: false,
        curve: 'basis',
      },
      sequence: {
        useMaxWidth: true,
      },
    };
  }

  function clamp(value, min, max) {
    return Math.max(min, Math.min(max, value));
  }

  function applyZoom(node, scale) {
    node.style.transformOrigin = 'top left';
    node.style.transform = `scale(${scale})`;
    node.dataset.mermaidZoom = String(scale);
  }

  function ensureDiagramControls(shell, node) {
    if (!shell || shell.querySelector('[data-mermaid-controls]')) {
      return;
    }

    const controls = document.createElement('div');
    controls.className = 'world-game-flow__mermaid-controls';
    controls.setAttribute('data-mermaid-controls', 'true');

    const zoomOut = document.createElement('button');
    zoomOut.type = 'button';
    zoomOut.className = 'btn btn-sm btn-outline-secondary';
    zoomOut.textContent = '-';
    zoomOut.setAttribute('aria-label', 'Zoom out diagram');

    const reset = document.createElement('button');
    reset.type = 'button';
    reset.className = 'btn btn-sm btn-outline-secondary';
    reset.textContent = 'Reset';
    reset.setAttribute('aria-label', 'Reset diagram zoom');

    const zoomIn = document.createElement('button');
    zoomIn.type = 'button';
    zoomIn.className = 'btn btn-sm btn-outline-secondary';
    zoomIn.textContent = '+';
    zoomIn.setAttribute('aria-label', 'Zoom in diagram');

    const level = document.createElement('span');
    level.className = 'world-game-flow__mermaid-zoom-level';
    level.textContent = '100%';
    level.setAttribute('aria-live', 'polite');

    const setScale = (nextScale) => {
      const scale = clamp(nextScale, 0.5, 2.5);
      applyZoom(node, scale);
      level.textContent = `${Math.round(scale * 100)}%`;
    };

    zoomOut.addEventListener('click', () => {
      const current = parseFloat(node.dataset.mermaidZoom || '1');
      setScale(current - 0.15);
    });

    zoomIn.addEventListener('click', () => {
      const current = parseFloat(node.dataset.mermaidZoom || '1');
      setScale(current + 0.15);
    });

    reset.addEventListener('click', () => {
      setScale(1);
      shell.scrollTo({ left: 0, top: 0, behavior: 'smooth' });
    });

    shell.insertBefore(controls, shell.firstChild);
    controls.appendChild(zoomOut);
    controls.appendChild(reset);
    controls.appendChild(zoomIn);
    controls.appendChild(level);

    applyZoom(node, 1);
  }

  function renderMermaidError(node, source, error) {
    node.classList.add('world-game-flow__mermaid--error');
    node.classList.remove('mermaid');
    node.innerHTML = '';

    const message = document.createElement('div');
    message.className = 'alert alert-warning py-2 px-3 mb-2';
    const detail = error && typeof error.message === 'string' ? error.message : 'Unknown Mermaid parsing/rendering error.';
    message.textContent = `Diagram render failed: ${detail}`;

    const pre = document.createElement('pre');
    pre.className = 'world-game-flow__mermaid-source';
    pre.textContent = source;

    node.appendChild(message);
    node.appendChild(pre);
  }

  async function renderMermaidNode(node) {
    if (!node) {
      return;
    }

    const source = node.textContent ? node.textContent.trim() : '';
    if (!source) {
      return;
    }

    try {
      const renderId = `dc-world-flow-${Date.now()}-${mermaidRenderCounter++}`;
      const rendered = await window.mermaid.render(renderId, source);
      node.classList.remove('world-game-flow__mermaid--error');
      node.classList.add('mermaid');
      node.innerHTML = rendered.svg;

      if (typeof rendered.bindFunctions === 'function') {
        rendered.bindFunctions(node);
      }
    }
    catch (error) {
      // Keep page usable and show the exact failing source instead of blank space.
      renderMermaidError(node, source, error);
      // eslint-disable-next-line no-console
      console.error('Mermaid diagram render failed', error);
    }
  }

  Drupal.behaviors.dungeoncrawlerWorldGameFlow = {
    attach(context) {
      if (!window.mermaid) {
        return;
      }

      if (!mermaidInitialized) {
        const root = context.querySelector?.('.world-game-flow') || document.querySelector('.world-game-flow');
        window.mermaid.initialize(buildMermaidTheme(root));
        mermaidInitialized = true;
      }

      const nodes = once('dungeoncrawler-world-game-flow', '[data-mermaid-diagram]', context);
      if (!nodes.length) {
        return;
      }

      nodes.forEach((node) => {
        const shell = node.closest('.world-game-flow__mermaid-shell');
        if (shell) {
          ensureDiagramControls(shell, node);
        }
        void renderMermaidNode(node);
      });
    },
  };
})(Drupal, once);
