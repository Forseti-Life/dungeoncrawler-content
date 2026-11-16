let renderSequence = 0;
let initialized = false;

function label(text) {
  const lines = [''];
  for (const word of String(text).split(/\s+/)) {
    if (lines.at(-1).length + word.length > 28 && lines.at(-1)) lines.push('');
    lines[lines.length - 1] += `${lines.at(-1) ? ' ' : ''}${word}`;
  }
  return lines.map((line) => line.replace(/[&"<>\\#]/g, (character) => `#${character.codePointAt(0)};`)).join('<br/>');
}

export function buildStorylineFlowSource(graph) {
  const nodes = graph?.nodes || [];
  const edges = graph?.edges || [];
  const ids = new Map();
  const lines = ['flowchart TB'];
  const colors = {
    chapter: '#f0c87a', scene: '#4fc3f7', actor: '#81c784',
    conflict: '#ef6c6c', quest: '#ce93d8', decision: '#ffb74d',
    choice: '#4dd0e1', environment: '#aed581',
    storyline_implication: '#a5b4fc', terminal_outcome: '#fca5a5',
  };
  for (const [index, node] of nodes.entries()) {
    if (!node.id || ids.has(String(node.id))) throw new Error('storyline_flow_node_id_invalid');
    const id = `n${index}`;
    ids.set(String(node.id), id);
    const type = Object.hasOwn(colors, node.type) ? node.type : 'default';
    const text = label(`${node.approach?.visibility ? `[${node.approach.visibility}] ` : ''}${node.label || node.id}`);
    lines.push(`${id}["${text}"]:::${type}`);
  }
  for (const edge of edges) {
    const source = ids.get(String(edge.source));
    const target = ids.get(String(edge.target));
    if (!source || !target) throw new Error('storyline_flow_edge_target_missing');
    const text = edge.label || (edge.type === 'sequence' ? '' : String(edge.type || '').replaceAll('_', ' '));
    lines.push(`${source} -->${text ? `|"${label(text)}"|` : ''} ${target}`);
  }
  for (const [type, color] of Object.entries(colors)) {
    lines.push(`classDef ${type} fill:${color},stroke:#374151,color:#111827`);
  }
  return lines.join('\n');
}

export class StorylineFlowGraph {
  constructor(container) {
    this.container = container;
    this.request = 0;
    this.controller = null;
    this.fitZoom = 1;
    const controls = document.createElement('div');
    controls.className = 'gm-storyline-wizard__graph-controls';
    controls.setAttribute('role', 'group');
    controls.setAttribute('aria-label', 'Flowchart controls');
    this.viewport = document.createElement('div');
    this.viewport.className = 'gm-storyline-wizard__graph-viewport';
    this.viewport.tabIndex = 0;
    this.viewport.setAttribute('aria-label', 'Storyline flowchart. Drag to pan, scroll to zoom, or use plus, minus and arrow keys. Press Home to fit.');
    this.level = document.createElement('output');
    this.level.setAttribute('aria-label', 'Chart zoom');
    this.buttons = [];
    for (const [text, title, action] of [
      ['-', 'Zoom out storyline flow', () => this.controller?.zoomOut()],
      ['Fit', 'Fit storyline flow to pane', () => this.fit()],
      ['+', 'Zoom in storyline flow', () => this.controller?.zoomIn()],
    ]) {
      const button = document.createElement('button');
      button.type = 'button';
      button.textContent = text;
      button.setAttribute('aria-label', title);
      button.disabled = true;
      button.addEventListener('click', action);
      this.buttons.push(button);
      controls.appendChild(button);
    }
    const hint = document.createElement('span');
    hint.textContent = 'Drag to pan / Scroll to zoom';
    controls.append(this.level, hint);
    this.container.replaceChildren(controls, this.viewport);
    this.viewport.addEventListener('keydown', (event) => {
      if (!this.controller) return;
      const actions = {
        '+': () => this.controller.zoomIn(),
        '=': () => this.controller.zoomIn(),
        '-': () => this.controller.zoomOut(),
        '0': () => this.fit(),
        Home: () => this.fit(),
        ArrowLeft: () => this.controller.panBy({ x: 40, y: 0 }),
        ArrowRight: () => this.controller.panBy({ x: -40, y: 0 }),
        ArrowUp: () => this.controller.panBy({ x: 0, y: 40 }),
        ArrowDown: () => this.controller.panBy({ x: 0, y: -40 }),
      };
      if (actions[event.key]) {
        event.preventDefault();
        actions[event.key]();
      }
    });
    this.observer = new ResizeObserver(() => this.resize());
    this.observer.observe(this.viewport);
  }

  async render(graph) {
    const request = ++this.request;
    this.controller?.destroy();
    this.controller = null;
    this.buttons.forEach((button) => { button.disabled = true; });
    if (!graph?.nodes?.length) {
      this.viewport.textContent = 'No storyline flow has been generated.';
      this.level.textContent = '';
      return;
    }
    this.viewport.textContent = 'Laying out storyline flow...';
    try {
      if (!window.mermaid || !window.svgPanZoom) throw new Error('storyline_flow_framework_unavailable');
      if (!initialized) {
        window.mermaid.initialize({
          startOnLoad: false,
          securityLevel: 'strict',
          theme: 'dark',
          flowchart: { useMaxWidth: false, htmlLabels: false, curve: 'basis', nodeSpacing: 25, rankSpacing: 45 },
        });
        initialized = true;
      }
      const rendered = await window.mermaid.render(`storyline-flow-${++renderSequence}`, buildStorylineFlowSource(graph));
      if (request !== this.request) return;
      this.viewport.innerHTML = rendered.svg;
      const svg = this.viewport.querySelector('svg');
      svg.style.maxWidth = 'none';
      svg.setAttribute('width', '100%');
      svg.setAttribute('height', '100%');
      svg.setAttribute('role', 'img');
      svg.setAttribute('aria-label', 'Storyline progression and relationships');
      this.resize();
    } catch (error) {
      if (request === this.request) this.viewport.textContent = `Storyline flow could not be rendered: ${error.message}`;
      throw error;
    }
  }

  resize() {
    const svg = this.viewport.querySelector('svg');
    if (!svg || this.viewport.clientWidth === 0 || this.viewport.clientHeight === 0) return;
    if (!this.controller) {
      this.controller = window.svgPanZoom(svg, {
        fit: true, center: true, minZoom: 0.1, maxZoom: 12,
        controlIconsEnabled: false, zoomScaleSensitivity: 0.2,
        mouseWheelZoomEnabled: true,
        onZoom: (zoom) => { this.level.textContent = `${Math.round(zoom / this.fitZoom * 100)}%`; },
      });
      this.buttons.forEach((button) => { button.disabled = false; });
    } else {
      this.controller.resize();
    }
    this.fit();
  }

  fit() {
    if (!this.controller) return;
    this.controller.fit();
    this.controller.center();
    this.fitZoom = this.controller.getZoom();
    this.level.textContent = '100%';
  }
}
