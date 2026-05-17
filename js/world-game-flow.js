(function (Drupal, once) {
  let mermaidInitialized = false;

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
        node.classList.add('mermaid');
      });

      window.mermaid.run({ nodes });
    },
  };
})(Drupal, once);
