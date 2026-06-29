import { chromium } from 'playwright';
import fs from 'node:fs/promises';

const uliUrl = process.env.ULI_URL;
const baseUrl = process.env.BASE_URL || 'http://127.0.0.1';

if (!uliUrl) {
  console.error('Missing ULI_URL env var');
  process.exit(1);
}

const browser = await chromium.launch({ headless: true });
const context = await browser.newContext();
const page = await context.newPage();

const logs = [];
page.on('console', (msg) => logs.push(`console:${msg.type()}: ${msg.text()}`));
page.on('pageerror', (err) => logs.push(`pageerror: ${err.message}`));

try {
  await page.goto(uliUrl, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1500);

  await page.goto(`${baseUrl}/jobhunter`, { waitUntil: 'domcontentloaded', timeout: 120000 });
  await page.waitForTimeout(1500);

  const title = await page.title();
  const headingTexts = await page.locator('h1, h2, h3').allInnerTexts();
  const links = await page.evaluate(() => {
    return Array.from(document.querySelectorAll('a'))
      .map((a) => ({ text: (a.textContent || '').trim(), href: a.href }))
      .filter((l) => l.text || l.href)
      .slice(0, 300);
  });

  await page.screenshot({ path: 'testing/artifacts/jobhunter-dashboard.png', fullPage: true });

  const payload = {
    title,
    url: page.url(),
    headings: headingTexts,
    links,
    logs,
  };

  await fs.writeFile('testing/artifacts/jobhunter-discovery.json', JSON.stringify(payload, null, 2));
  console.log('Discovery written: testing/artifacts/jobhunter-discovery.json');
  console.log(`Final URL: ${page.url()}`);
} finally {
  await browser.close();
}
