#!/usr/bin/env node

/**
 * Console Capture Utility
 * 
 * Captures browser console output (logs, errors, warnings) using Playwright.
 * Useful for automated testing and debugging without manual browser inspection.
 * 
 * Usage:
 *   node capture-console.js <url> [timeout]
 *   node capture-console.js http://localhost:8080/hexmap 5000
 */

const { chromium } = require('playwright');
const fs = require('fs');
const path = require('path');

async function captureConsole(url, timeout = 10000, outputFile = null) {
  const browser = await chromium.launch();
  const context = await browser.newContext();
  const page = await context.newPage();

  // Arrays to store console messages
  const logs = {
    log: [],
    error: [],
    warning: [],
    info: [],
    debug: [],
    network: []
  };

  // Listen to console events
  page.on('console', async msg => {
    const args = [];
    for (let i = 0; i < msg.args().length; ++i) {
      try {
        args.push(await msg.args()[i].jsonValue());
      } catch (e) {
        args.push(`[Argument ${i} could not be serialized]`);
      }
    }
    
    const type = msg.type();
    const entry = {
      type,
      text: msg.text(),
      location: msg.location(),
      args,
      timestamp: new Date().toISOString()
    };

    if (logs[type]) {
      logs[type].push(entry);
    }
    
    // Print to stdout immediately
    console.log(`[${type.toUpperCase()}] ${msg.text()}`);
  });

  // Listen to page errors
  page.on('pageerror', error => {
    const errorEntry = {
      type: 'pageerror',
      message: error.message,
      stack: error.stack,
      timestamp: new Date().toISOString()
    };
    logs.error.push(errorEntry);
    console.error(`[PAGEERROR] ${error.message}`);
  });

  // Listen to request/response for network logs
  page.on('response', response => {
    if (response.status() >= 400) {
      const networkEntry = {
        type: 'network-error',
        url: response.url(),
        status: response.status(),
        statusText: response.statusText(),
        timestamp: new Date().toISOString()
      };
      logs.network.push(networkEntry);
      console.warn(`[NETWORK] ${response.status()} ${response.url()}`);
    }
  });

  try {
    console.log(`[INFO] Loading page: ${url}`);
    await page.goto(url, { waitUntil: 'networkidle' });
    
    // Wait for specified timeout to capture dynamic console output
    await page.waitForTimeout(timeout);
    
    console.log(`[INFO] Page loaded and waited ${timeout}ms for dynamic output`);
  } catch (error) {
    console.error(`[ERROR] Failed to load page: ${error.message}`);
    logs.error.push({
      type: 'load-error',
      message: error.message,
      timestamp: new Date().toISOString()
    });
  }

  await context.close();
  await browser.close();

  // Format output
  const output = {
    url,
    capturedAt: new Date().toISOString(),
    summary: {
      totalErrors: logs.error.length,
      totalWarnings: logs.warning.length,
      totalLogs: logs.log.length,
      networkErrors: logs.network.length
    },
    logs
  };

  // Save to file if specified
  if (outputFile) {
    const fullPath = path.resolve(outputFile);
    fs.writeFileSync(fullPath, JSON.stringify(output, null, 2));
    console.log(`\n[INFO] Console logs saved to: ${fullPath}`);
  }

  return output;
}

// Main execution
const url = process.argv[2] || 'http://localhost:8080/hexmap';
const timeout = parseInt(process.argv[3]) || 10000;
const outputFile = process.argv[4] || null;

console.log('=== Playwright Console Capture ===\n');
captureConsole(url, timeout, outputFile)
  .then(result => {
    console.log('\n=== Summary ===');
    console.log(`Errors: ${result.summary.totalErrors}`);
    console.log(`Warnings: ${result.summary.totalWarnings}`);
    console.log(`Logs: ${result.summary.totalLogs}`);
    console.log(`Network errors: ${result.summary.networkErrors}`);
    
    if (result.summary.totalErrors > 0) {
      console.log('\n=== Errors ===');
      result.logs.error.forEach(err => {
        console.log(`- ${err.message || err.text}`);
      });
      process.exit(1);
    } else {
      console.log('\n✓ No errors detected');
      process.exit(0);
    }
  })
  .catch(error => {
    console.error('Fatal error:', error);
    process.exit(1);
  });
