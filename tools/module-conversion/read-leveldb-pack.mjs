import fs from 'node:fs';
import path from 'node:path';
import { ClassicLevel } from 'classic-level';

function parseArgs(argv) {
  const args = {
    dbDir: '',
    maxEntries: 25000,
    maxValueBytes: 8 * 1024 * 1024,
    maxDocuments: 30000,
  };
  for (let index = 0; index < argv.length; index += 1) {
    const token = argv[index];
    const next = argv[index + 1];
    if (token === '--db-dir') {
      args.dbDir = next ?? '';
      index += 1;
      continue;
    }
    if (token === '--max-entries') {
      args.maxEntries = Number.parseInt(next ?? '', 10);
      index += 1;
      continue;
    }
    if (token === '--max-value-bytes') {
      args.maxValueBytes = Number.parseInt(next ?? '', 10);
      index += 1;
      continue;
    }
    if (token === '--max-documents') {
      args.maxDocuments = Number.parseInt(next ?? '', 10);
      index += 1;
    }
  }
  return args;
}

function fail(message, context = {}) {
  process.stderr.write(`${message}\n`);
  if (Object.keys(context).length > 0) {
    process.stderr.write(`${JSON.stringify(context)}\n`);
  }
  process.exit(1);
}

function toUtf8(value) {
  return Buffer.isBuffer(value) ? value.toString('utf8') : String(value);
}

function extractCollection(key) {
  const match = /^!([^!]+)!/.exec(key);
  if (!match) {
    return { raw: 'unclassified', top: 'unclassified', embedded: false };
  }
  const raw = match[1];
  const dot = raw.indexOf('.');
  if (dot < 0) {
    return { raw, top: raw, embedded: false };
  }
  return { raw, top: raw.slice(0, dot), embedded: true };
}

function countStringReferences(text, references) {
  if (text.includes('@UUID[')) {
    references.uuid += (text.match(/@UUID\[/g) ?? []).length || 1;
    const matches = text.match(/@UUID\[[^\]]+\]/g) ?? [];
    for (const match of matches) {
      if (references.uuidSamples.length < 200) {
        references.uuidSamples.push(match);
      }
    }
  }
  if (text.includes('Compendium.')) {
    references.compendium += (text.match(/Compendium\./g) ?? []).length || 1;
    const matches = text.match(/Compendium\.[A-Za-z0-9._-]+/g) ?? [];
    for (const match of matches) {
      if (references.compendiumSamples.length < 200) {
        references.compendiumSamples.push(match);
      }
    }
  }
  if (text.includes('modules/')) {
    references.modulesPath += (text.match(/modules\//g) ?? []).length || 1;
    const matches = text.match(/modules\/[A-Za-z0-9._\-\/]+/g) ?? [];
    for (const match of matches) {
      if (references.modulesPathSamples.length < 200) {
        references.modulesPathSamples.push(match);
      }
    }
  }
}

function inspectRuleElements(document, ruleElementCounts, references, scan) {
  const stack = [document];
  let visited = 0;
  while (stack.length > 0) {
    const current = stack.pop();
    visited += 1;
    if (visited > 1000000) {
      scan.traversalTruncated = true;
      return;
    }
    if (Array.isArray(current)) {
      for (const item of current) {
        stack.push(item);
      }
      continue;
    }
    if (!current || typeof current !== 'object') {
      if (typeof current === 'string') {
        countStringReferences(current, references);
      }
      continue;
    }
    if (Array.isArray(current.rules)) {
      for (const rule of current.rules) {
        if (rule && typeof rule === 'object' && typeof rule.key === 'string') {
          ruleElementCounts[rule.key] = (ruleElementCounts[rule.key] ?? 0) + 1;
        }
      }
    }
    for (const [key, value] of Object.entries(current)) {
      if (typeof value === 'string') {
        countStringReferences(value, references);
      }
      else {
        stack.push(value);
      }
      if (key === 'uuid' && typeof value === 'string') {
        references.uuidFields += 1;
      }
    }
  }
}

function summarizeAdventure(document, key) {
  const arrayFields = [
    'actors',
    'cards',
    'combats',
    'folders',
    'items',
    'journal',
    'macros',
    'playlists',
    'rolltables',
    'scenes',
    'tables',
  ];
  const counts = {};
  for (const field of arrayFields) {
    const value = document[field];
    if (Array.isArray(value) && value.length > 0) {
      counts[field] = value.length;
    }
  }
  return {
    key,
    id: typeof document._id === 'string' ? document._id : null,
    counts,
  };
}

function embedCollectionMap(parentCollection) {
  const normalized = String(parentCollection || '').toLowerCase();
  if (normalized === 'adventures' || normalized === 'adventure') {
    return {
      scenes: 'scenes',
      actors: 'actors',
      items: 'items',
      journal: 'journal',
      journals: 'journal',
      tables: 'rolltables',
      rolltables: 'rolltables',
      folders: 'folders',
    };
  }
  if (normalized === 'actors') {
    return { items: 'actors.items' };
  }
  if (normalized === 'journal') {
    return { pages: 'journal.pages' };
  }
  if (normalized === 'rolltables' || normalized === 'tables') {
    return { results: 'rolltables.results' };
  }
  return {};
}

function summarizeDocument(document, key, collection, size, references, ruleElementCounts, scan, parentKey = null) {
  const count = (value) => Array.isArray(value) ? value.length : 0;
  const refsBefore = {
    uuid: references.uuid,
    compendium: references.compendium,
    modulesPath: references.modulesPath,
    uuidSamples: references.uuidSamples.length,
    compendiumSamples: references.compendiumSamples.length,
    modulesPathSamples: references.modulesPathSamples.length,
  };
  const ruleKeysBefore = Object.keys(ruleElementCounts).length;
  inspectRuleElements(document, ruleElementCounts, references, scan);
  const summary = {
    key,
    collection: collection.raw,
    topCollection: collection.raw,
    embedded: collection.embedded,
    id: typeof document._id === 'string' ? document._id : null,
    name: typeof document.name === 'string' ? document.name : null,
    documentType: typeof document.type === 'string' ? document.type : null,
    parentKey,
    bytes: size,
    refs: {
      uuid: references.uuid - refsBefore.uuid,
      compendium: references.compendium - refsBefore.compendium,
      modulesPath: references.modulesPath - refsBefore.modulesPath,
      uuidSamples: Array.from(new Set(references.uuidSamples.slice(refsBefore.uuidSamples))),
      compendiumSamples: Array.from(new Set(references.compendiumSamples.slice(refsBefore.compendiumSamples))),
      modulesPathSamples: Array.from(new Set(references.modulesPathSamples.slice(refsBefore.modulesPathSamples))),
    },
    counts: {},
  };
  if (collection.top === 'scenes') {
    for (const field of ['width', 'height']) {
      if (document[field] != null && (!Number.isInteger(document[field]) || document[field] <= 0)) {
        throw new Error(`scene_source_geometry_invalid:${field}`);
      }
    }
    const grid = document.grid && typeof document.grid === 'object' && !Array.isArray(document.grid)
      ? document.grid : {};
    if (document.grid != null && (typeof document.grid !== 'object' || Array.isArray(document.grid))) {
      throw new Error('scene_source_geometry_invalid:grid');
    }
    if (grid.type != null && typeof grid.type !== 'string' && !Number.isInteger(grid.type)) {
      throw new Error('scene_source_geometry_invalid:grid.type');
    }
    if (grid.size != null && (!Number.isInteger(grid.size) || grid.size <= 0)) {
      throw new Error('scene_source_geometry_invalid:grid.size');
    }
    if (grid.distance != null && (typeof grid.distance !== 'number' || !Number.isFinite(grid.distance) || grid.distance <= 0)) {
      throw new Error('scene_source_geometry_invalid:grid.distance');
    }
    if (grid.units != null && typeof grid.units !== 'string') {
      throw new Error('scene_source_geometry_invalid:grid.units');
    }
    const background = document.background && typeof document.background === 'object' && !Array.isArray(document.background)
      ? document.background : {};
    if (document.background != null && (typeof document.background !== 'object' || Array.isArray(document.background))) {
      throw new Error('scene_source_asset_invalid:background');
    }
    if (background.src != null && typeof background.src !== 'string') {
      throw new Error('scene_source_asset_invalid:background.src');
    }
    if (document.navOrder != null && !Number.isInteger(document.navOrder)) {
      throw new Error('scene_source_geometry_invalid:navOrder');
    }
    for (const field of ['tokens', 'walls', 'drawings', 'tiles', 'lights', 'sounds', 'notes']) {
      if (document[field] != null && !Array.isArray(document[field])) {
        throw new Error(`scene_source_objects_invalid:${field}`);
      }
    }
    const tileImageRefs = [];
    const tilePlacements = [];
    for (const tile of document.tiles ?? []) {
      if (tile === null || typeof tile !== 'object' || Array.isArray(tile) ||
          (tile.texture != null && (typeof tile.texture !== 'object' || Array.isArray(tile.texture)))) {
        throw new Error('scene_source_asset_invalid:tile');
      }
      const src = tile.texture?.src ?? tile.img;
      if (src != null && typeof src !== 'string') {
        throw new Error('scene_source_asset_invalid:tile.src');
      }
      tileImageRefs.push(src ?? null);
      const placement = {};
      for (const field of ['x', 'y', 'width', 'height', 'rotation', 'alpha', 'elevation', 'sort']) {
        const value = tile[field];
        if (value != null && (typeof value !== 'number' || !Number.isFinite(value)
          || (['width', 'height'].includes(field) && value <= 0)
          || (field === 'alpha' && (value < 0 || value > 1)))) {
          throw new Error(`scene_source_geometry_invalid:tile.${field}`);
        }
        placement[field] = value ?? null;
      }
      tilePlacements.push(placement);
    }
    summary.source_context = {
      width: document.width ?? null,
      height: document.height ?? null,
      grid_type: grid.type ?? null,
      grid_size: grid.size ?? null,
      grid_distance: grid.distance ?? null,
      grid_units: grid.units ?? null,
      background_src: background.src ?? null,
      tile_image_refs: tileImageRefs,
      tile_placements: tilePlacements,
      nav_order: document.navOrder ?? null,
    };
  }
  if (collection.raw === 'journal.pages') {
    const text = document.text && typeof document.text === 'object' && !Array.isArray(document.text)
      ? document.text : {};
    summary.source_context = {
      text_content_length: typeof text.content === 'string' ? text.content.length : 0,
      markdown_length: typeof text.markdown === 'string' ? text.markdown.length : 0,
    };
  }
  const counts = summary.counts;
  const fields = ['items', 'actors', 'scenes', 'tables', 'journal', 'pages', 'notes', 'tokens', 'walls', 'drawings', 'tiles', 'lights', 'sounds', 'results', 'folders'];
  for (const field of fields) {
    const value = count(document[field]);
    if (value > 0) {
      counts[field] = value;
    }
  }
  const ruleKeysAfter = Object.keys(ruleElementCounts).length;
  if (ruleKeysAfter > ruleKeysBefore) {
    counts.ruleElementKeys = ruleKeysAfter - ruleKeysBefore;
  }
  return summary;
}

function flattenEmbeddedDocuments(document, parentSummary, references, ruleElementCounts, scan) {
  const output = [];
  const map = embedCollectionMap(parentSummary.collection);
  for (const [field, collectionName] of Object.entries(map)) {
    const children = document[field];
    if (!Array.isArray(children)) {
      continue;
    }
    for (let index = 0; index < children.length; index += 1) {
      const child = children[index];
      if (!child || typeof child !== 'object' || Array.isArray(child)) {
        continue;
      }
      const key = `${parentSummary.key}/${field}/${index}`;
      const childCollection = {
        raw: collectionName,
        top: String(collectionName).split('.')[0] || collectionName,
        embedded: true,
      };
      const size = Buffer.byteLength(JSON.stringify(child));
      const summary = summarizeDocument(
        child,
        key,
        childCollection,
        size,
        references,
        ruleElementCounts,
        scan,
        parentSummary.key,
      );
      output.push(summary);
      output.push(...flattenEmbeddedDocuments(child, summary, references, ruleElementCounts, scan));
    }
  }
  return output;
}

async function main() {
  const parsed = parseArgs(process.argv.slice(2));
  if (!parsed.dbDir) {
    fail('Missing required --db-dir argument.');
  }
  if (!Number.isInteger(parsed.maxEntries) || parsed.maxEntries < 1 || parsed.maxEntries > 500000) {
    fail('Invalid --max-entries; expected integer 1..500000.', { value: parsed.maxEntries });
  }
  if (!Number.isInteger(parsed.maxValueBytes) || parsed.maxValueBytes < 512 || parsed.maxValueBytes > 16 * 1024 * 1024) {
    fail('Invalid --max-value-bytes; expected integer 512..16777216.', { value: parsed.maxValueBytes });
  }
  if (!Number.isInteger(parsed.maxDocuments) || parsed.maxDocuments < 1 || parsed.maxDocuments > 200000) {
    fail('Invalid --max-documents; expected integer 1..200000.', { value: parsed.maxDocuments });
  }

  const resolvedDir = path.resolve(parsed.dbDir);
  let stat;
  try {
    stat = fs.statSync(resolvedDir);
  }
  catch {
    fail('Pack directory is missing.', { dbDir: resolvedDir });
  }
  if (!stat.isDirectory()) {
    fail('Pack path must be a directory.', { dbDir: resolvedDir });
  }

  const summary = {
    reader: 'classic-level-v1',
    dbDir: resolvedDir,
    truncated: false,
    limits: {
      maxEntries: parsed.maxEntries,
      maxValueBytes: parsed.maxValueBytes,
      maxDocuments: parsed.maxDocuments,
    },
    scan: {
      entriesRead: 0,
      entriesSkippedOverLimit: 0,
      valueParseFailures: 0,
      maxValueBytesObserved: 0,
      documentsTruncated: false,
      traversalTruncated: false,
    },
    collections: {
      topLevel: {},
      embedded: {},
      unclassified: 0,
    },
    adventureSummaries: [],
    ruleElementCounts: {},
    references: {
      uuid: 0,
      uuidFields: 0,
      compendium: 0,
      modulesPath: 0,
      uuidSamples: [],
      compendiumSamples: [],
      modulesPathSamples: [],
    },
    documents: [],
  };

  const db = new ClassicLevel(resolvedDir, { keyEncoding: 'buffer', valueEncoding: 'buffer' });
  try {
    for await (const [keyBuffer, valueBuffer] of db.iterator({ fillCache: false })) {
      summary.scan.entriesRead += 1;
      if (summary.scan.entriesRead > parsed.maxEntries) {
        summary.truncated = true;
        break;
      }

      const key = toUtf8(keyBuffer);
      const collection = extractCollection(key);
      const size = Buffer.isBuffer(valueBuffer) ? valueBuffer.byteLength : Buffer.byteLength(toUtf8(valueBuffer));
      if (size > summary.scan.maxValueBytesObserved) {
        summary.scan.maxValueBytesObserved = size;
      }

      if (collection.raw === 'unclassified') {
        summary.collections.unclassified += 1;
      }
      else if (collection.embedded) {
        summary.collections.embedded[collection.raw] = (summary.collections.embedded[collection.raw] ?? 0) + 1;
      }
      else {
        summary.collections.topLevel[collection.top] = (summary.collections.topLevel[collection.top] ?? 0) + 1;
      }

      if (size > parsed.maxValueBytes) {
        summary.scan.entriesSkippedOverLimit += 1;
        continue;
      }

      let document;
      try {
        document = JSON.parse(toUtf8(valueBuffer));
      }
      catch {
        summary.scan.valueParseFailures += 1;
        continue;
      }
      const baseSummary = summarizeDocument(
        document,
        key,
        collection,
        size,
        summary.references,
        summary.ruleElementCounts,
        summary.scan,
      );
      const allSummaries = [baseSummary, ...flattenEmbeddedDocuments(document, baseSummary, summary.references, summary.ruleElementCounts, summary.scan)];
      for (const entry of allSummaries) {
        if (summary.documents.length < parsed.maxDocuments) {
          summary.documents.push(entry);
        }
        else {
          summary.scan.documentsTruncated = true;
          break;
        }
      }

      if (collection.top === 'adventures' && !collection.embedded) {
        summary.adventureSummaries.push(summarizeAdventure(document, key));
      }
    }
  }
  finally {
    await db.close();
  }

  process.stdout.write(`${JSON.stringify(summary)}\n`);
}

main().catch((error) => {
  fail('Unhandled LevelDB reader failure.', {
    message: error instanceof Error ? error.message : String(error),
  });
});
