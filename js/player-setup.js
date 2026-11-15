/**
 * Setup-only hex editor. Form API owns validation, CSRF, and persistence.
 */
(function (Drupal, once, drupalSettings) {
  Drupal.behaviors.playerSetup = {
    attach(context) {
      once('player-setup', '[data-player-setup]', context).forEach((form) => {
        const { room, roster } = drupalSettings.playerSetup;
        const map = form.querySelector('[data-player-setup-map]');
        const status = form.querySelector('[data-player-setup-status]');
        const ns = 'http://www.w3.org/2000/svg';
        const svg = document.createElementNS(ns, 'svg');
        svg.setAttribute('viewBox', '-330 -290 660 580');
        svg.setAttribute('role', 'group');
        svg.setAttribute('aria-label', room.name);
        map.append(svg);
        let selected = '';
        const fields = new Map(Array.from(form.querySelectorAll('[data-setup-position]'))
          .map((field) => [field.dataset.setupPosition, field]));
        const buttons = Array.from(form.querySelectorAll('[data-setup-actor]'));
        const cells = new Map();
        const point = (q, r) => ({ x: 45 * q, y: Math.sqrt(3) * 30 * (r + q / 2) });
        const setPosition = (key) => {
          if (!selected) {
            status.textContent = 'Select an actor before choosing a hex.';
            return;
          }
          const field = fields.get(selected);
          field.value = key;
          const checkbox = field.closest('tr').querySelector('input[type="checkbox"]');
          checkbox.checked = true;
          field.dispatchEvent(new Event('change', { bubbles: true }));
          status.textContent = `${roster[selected].name} placed at ${key.replace(':', ', ')}. Save this room to keep the change.`;
        };
        for (const hex of room.hexes) {
          const key = `${hex.q}:${hex.r}`;
          const { x, y } = point(hex.q, hex.r);
          const group = document.createElementNS(ns, 'g');
          group.setAttribute('role', 'button');
          group.setAttribute('tabindex', '0');
          group.setAttribute('aria-label', `Hex ${hex.q}, ${hex.r}`);
          group.dataset.setupHex = key;
          const polygon = document.createElementNS(ns, 'polygon');
          polygon.setAttribute('points', Array.from({ length: 6 }, (_, index) => {
            const angle = Math.PI * index / 3;
            return `${x + 29 * Math.cos(angle)},${y + 29 * Math.sin(angle)}`;
          }).join(' '));
          polygon.classList.add(hex.terrain_type === 'stone_floor' ? 'player-setup__road' : 'player-setup__grass');
          const text = document.createElementNS(ns, 'text');
          text.setAttribute('x', String(x));
          text.setAttribute('y', String(y + 4));
          text.setAttribute('text-anchor', 'middle');
          text.textContent = `${hex.q},${hex.r}`;
          group.append(polygon, text);
          group.addEventListener('click', () => setPosition(key));
          group.addEventListener('keydown', (event) => {
            if (event.key === 'Enter' || event.key === ' ') {
              event.preventDefault();
              setPosition(key);
            }
          });
          cells.set(key, { group, text, label: text.textContent });
          svg.append(group);
        }
        const refresh = () => {
          cells.forEach(({ group, text, label }) => {
            group.classList.remove('player-setup__occupied');
            group.setAttribute('aria-label', `Hex ${label}`);
            text.textContent = label;
          });
          const occupants = new Map();
          fields.forEach((field, id) => {
            if (!field.closest('tr').querySelector('input[type="checkbox"]').checked) return;
            const cell = cells.get(field.value);
            if (!cell) return;
            const names = occupants.get(field.value) || [];
            names.push(roster[id].name);
            occupants.set(field.value, names);
            cell.group.classList.add('player-setup__occupied');
            cell.text.textContent = names.map((name) => name.slice(0, 7)).join('/');
            cell.group.setAttribute('aria-label', `Hex ${field.value}: ${names.join(', ')}`);
          });
        };
        buttons.forEach((button) => button.addEventListener('click', () => {
          selected = button.dataset.setupActor;
          buttons.forEach((entry) => entry.setAttribute('aria-pressed', String(entry === button)));
          status.textContent = `Choose a hex for ${roster[selected].name}.`;
        }));
        form.addEventListener('change', refresh);
        refresh();
      });
    },
  };
})(Drupal, once, drupalSettings);
