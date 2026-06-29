import fs from 'node:fs/promises';
import path from 'node:path';
import process from 'node:process';
import { chromium, devices } from 'playwright';

const DEFAULT_HEXMAP_URL =
  'http://penguin.linux.test:8080/hexmap?campaign_id=2&character_id=2&dungeon_level_id=f8c6b8f1-2df9-469f-9fd5-67a59f120001&map_id=0b7e3d2f-8f7c-4ae0-8f72-9e99e0800001&room_id=7f2f1051-5f88-45a2-a66a-0f7063900001&next_room_id=7f2f1051-5f88-45a2-a66a-0f7063900002&start_q=0&start_r=0';

const outputRoot = path.resolve(
  process.cwd(),
  process.env.HEXMAP_REVIEW_OUTPUT_DIR || 'testing/results/hexmap-ui-review'
);

const timestamp = new Date().toISOString().replace(/[.:]/g, '-');
const runDir = path.join(outputRoot, timestamp);
const targetUrl = process.env.HEXMAP_REVIEW_URL || DEFAULT_HEXMAP_URL;

const viewportProfiles = [
  {
    key: 'desktop-1440x900',
    contextOptions: {
      viewport: { width: 1440, height: 900 },
      deviceScaleFactor: 1,
    },
  },
  {
    key: 'mobile-pixel-7',
    contextOptions: {
      ...devices['Pixel 7'],
    },
  },
];

async function captureProfile(browser, profile) {
  const context = await browser.newContext({
    ...profile.contextOptions,
    ignoreHTTPSErrors: true,
  });

  const page = await context.newPage();
  const events = [];

  page.on('console', (msg) => {
    events.push({
      type: 'console',
      level: msg.type(),
      text: msg.text(),
    });
  });

  page.on('pageerror', (error) => {
    events.push({
      type: 'pageerror',
      message: error.message,
    });
  });

  await page.goto(targetUrl, { waitUntil: 'networkidle', timeout: 60000 });
  await page.waitForTimeout(1500);

  const screenshotPath = path.join(runDir, `${profile.key}.png`);
  await page.screenshot({
    path: screenshotPath,
    fullPage: true,
    animations: 'disabled',
  });

  const pageState = {
    profile: profile.key,
    finalUrl: page.url(),
    title: await page.title(),
    capturedAt: new Date().toISOString(),
    screenshot: path.relative(process.cwd(), screenshotPath),
    events,
  };

  await fs.writeFile(
    path.join(runDir, `${profile.key}.json`),
    `${JSON.stringify(pageState, null, 2)}\n`,
    'utf8'
  );

  await context.close();

  return pageState;
}

async function main() {
  await fs.mkdir(runDir, { recursive: true });

  const browser = await chromium.launch({ headless: true });

  try {
    const captures = [];

    for (const profile of viewportProfiles) {
      const result = await captureProfile(browser, profile);
      captures.push(result);
      process.stdout.write(`Captured ${result.profile}: ${result.screenshot}\n`);
    }

    const summary = {
      targetUrl,
      outputDirectory: path.relative(process.cwd(), runDir),
      runAt: new Date().toISOString(),
      captures,
    };

    await fs.writeFile(
      path.join(runDir, 'summary.json'),
      `${JSON.stringify(summary, null, 2)}\n`,
      'utf8'
    );

    process.stdout.write(`\nUI review capture complete.\n`);
    process.stdout.write(`Output: ${summary.outputDirectory}\n`);
  } finally {
    await browser.close();
  }
}

main().catch((error) => {
  process.stderr.write(`Hexmap UI review capture failed: ${error.message}\n`);
  process.exitCode = 1;
});
