#!/usr/bin/env node

/**
 * Test hover tooltip functionality for AmISafe Crime Map
 * Tests both hover tooltips and enhanced popup content
 */

const puppeteer = require('puppeteer');
const fs = require('fs');
const path = require('path');

class TooltipTester {
  constructor() {
    this.browser = null;
    this.page = null;
    this.results = [];
    this.baseUrl = 'http://localhost/amisafe-crime-map';
  }

  async init() {
    console.log('🔍 Initializing tooltip functionality test...\n');
    
    this.browser = await puppeteer.launch({
      headless: false,
      defaultViewport: { width: 1200, height: 800 },
      args: ['--no-sandbox', '--disable-setuid-sandbox']
    });
    
    this.page = await this.browser.newPage();
    await this.page.setViewport({ width: 1200, height: 800 });
  }

  async testTooltipFunctionality() {
    console.log('📍 Testing tooltip functionality...');
    
    try {
      // Navigate to the page
      await this.page.goto(this.baseUrl, { waitUntil: 'networkidle0' });
      
      // Wait for map to load
      await this.page.waitForSelector('#crime-map', { timeout: 10000 });
      await this.page.waitForTimeout(3000);
      
      // Check if helper functions exist
      const helpersExist = await this.page.evaluate(() => {
        return typeof window.Drupal !== 'undefined' &&
               window.Drupal.behaviors &&
               window.Drupal.behaviors.amisafeCrimeMap &&
               typeof window.Drupal.behaviors.amisafeCrimeMap.calculateRiskLevel === 'function' &&
               typeof window.Drupal.behaviors.amisafeCrimeMap.getCrimeTypeName === 'function' &&
               typeof window.Drupal.behaviors.amisafeCrimeMap.createHoverTooltip === 'function';
      });
      
      this.logResult('Helper Functions Exist', helpersExist, 
        'calculateRiskLevel, getCrimeTypeName, and createHoverTooltip functions are available');
      
      // Test risk level calculation
      const riskLevels = await this.page.evaluate(() => {
        const behavior = window.Drupal.behaviors.amisafeCrimeMap;
        return {
          critical: behavior.calculateRiskLevel(1500),
          high: behavior.calculateRiskLevel(750),
          medium: behavior.calculateRiskLevel(150),
          low: behavior.calculateRiskLevel(50),
          minimal: behavior.calculateRiskLevel(5)
        };
      });
      
      const expectedRisks = {
        critical: 'CRITICAL',
        high: 'HIGH', 
        medium: 'MEDIUM',
        low: 'LOW',
        minimal: 'MINIMAL'
      };
      
      const riskTestPassed = JSON.stringify(riskLevels) === JSON.stringify(expectedRisks);
      this.logResult('Risk Level Calculation', riskTestPassed, 
        `Expected: ${JSON.stringify(expectedRisks)}, Got: ${JSON.stringify(riskLevels)}`);
      
      // Test crime type name mapping
      const crimeNames = await this.page.evaluate(() => {
        const behavior = window.Drupal.behaviors.amisafeCrimeMap;
        return {
          burg: behavior.getCrimeTypeName('BURG'),
          thef: behavior.getCrimeTypeName('THEF'),
          unknown: behavior.getCrimeTypeName('UNKNOWN_CODE')
        };
      });
      
      const crimeNamesCorrect = crimeNames.burg === 'Burglary' && 
                               crimeNames.thef === 'Theft' &&
                               crimeNames.unknown === 'UNKNOWN_CODE';
      
      this.logResult('Crime Type Name Mapping', crimeNamesCorrect,
        `Burglary: ${crimeNames.burg}, Theft: ${crimeNames.thef}, Unknown: ${crimeNames.unknown}`);
      
      // Test tooltip content generation
      const tooltipContent = await this.page.evaluate(() => {
        const behavior = window.Drupal.behaviors.amisafeCrimeMap;
        const mockHexagon = {
          incident_count: 250,
          resolution: 7,
          unique_incident_types: 8
        };
        return behavior.createHoverTooltip(mockHexagon);
      });
      
      const tooltipHasContent = tooltipContent.includes('H3:7 Sector') &&
                               tooltipContent.includes('250 incidents') &&
                               tooltipContent.includes('8 crime types') &&
                               tooltipContent.includes('MEDIUM risk');
      
      this.logResult('Tooltip Content Generation', tooltipHasContent,
        'Tooltip includes resolution, incident count, crime types, and risk level');
      
      // Wait for hexagons to load
      console.log('⏳ Waiting for hexagons to load...');
      await this.page.waitForTimeout(5000);
      
      // Check if hexagons have tooltips
      const hexagonCount = await this.page.evaluate(() => {
        return document.querySelectorAll('.leaflet-interactive').length;
      });
      
      this.logResult('Hexagons Loaded', hexagonCount > 0, 
        `Found ${hexagonCount} interactive hexagon elements`);
      
      if (hexagonCount > 0) {
        // Test hover interaction on first hexagon
        console.log('🖱️  Testing hover interaction...');
        
        const hoverResult = await this.page.evaluate(() => {
          return new Promise((resolve) => {
            const hexagons = document.querySelectorAll('.leaflet-interactive');
            if (hexagons.length === 0) {
              resolve(false);
              return;
            }
            
            const firstHexagon = hexagons[0];
            let tooltipAppeared = false;
            
            // Create mouseover event
            const mouseoverEvent = new MouseEvent('mouseover', {
              view: window,
              bubbles: true,
              cancelable: true
            });
            
            // Listen for tooltip appearance
            const observer = new MutationObserver((mutations) => {
              mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                  if (node.nodeType === 1 && 
                      (node.classList.contains('leaflet-tooltip') || 
                       node.classList.contains('hexagon-tooltip'))) {
                    tooltipAppeared = true;
                    observer.disconnect();
                    resolve(true);
                  }
                });
              });
            });
            
            observer.observe(document.body, {
              childList: true,
              subtree: true
            });
            
            // Trigger hover
            firstHexagon.dispatchEvent(mouseoverEvent);
            
            // Timeout after 2 seconds
            setTimeout(() => {
              observer.disconnect();
              resolve(tooltipAppeared);
            }, 2000);
          });
        });
        
        this.logResult('Hover Tooltip Interaction', hoverResult,
          'Tooltip appears when hovering over hexagon');
        
        // Test popup content by clicking
        console.log('🖱️  Testing popup interaction...');
        
        const popupResult = await this.page.evaluate(() => {
          return new Promise((resolve) => {
            const hexagons = document.querySelectorAll('.leaflet-interactive');
            if (hexagons.length === 0) {
              resolve(false);
              return;
            }
            
            const firstHexagon = hexagons[0];
            let popupAppeared = false;
            
            // Create click event
            const clickEvent = new MouseEvent('click', {
              view: window,
              bubbles: true,
              cancelable: true
            });
            
            // Listen for popup appearance
            const observer = new MutationObserver((mutations) => {
              mutations.forEach((mutation) => {
                mutation.addedNodes.forEach((node) => {
                  if (node.nodeType === 1 && 
                      (node.classList.contains('leaflet-popup') ||
                       node.querySelector('.hexagon-popup-content'))) {
                    popupAppeared = true;
                    observer.disconnect();
                    resolve(true);
                  }
                });
              });
            });
            
            observer.observe(document.body, {
              childList: true,
              subtree: true
            });
            
            // Trigger click
            firstHexagon.dispatchEvent(clickEvent);
            
            // Timeout after 3 seconds
            setTimeout(() => {
              observer.disconnect();
              resolve(popupAppeared);
            }, 3000);
          });
        });
        
        this.logResult('Enhanced Popup Content', popupResult,
          'Enhanced popup appears when clicking hexagon');
      }
      
      // Test CSS styling
      const cssLoaded = await this.page.evaluate(() => {
        // Check if tooltip styles are loaded
        const styles = window.getComputedStyle(document.documentElement);
        return document.querySelector('link[href*="professional-theme.css"]') !== null;
      });
      
      this.logResult('CSS Styling Loaded', cssLoaded,
        'Professional theme CSS with tooltip styles is loaded');
      
    } catch (error) {
      this.logResult('Tooltip Testing', false, `Error: ${error.message}`);
    }
  }

  logResult(testName, passed, details) {
    const status = passed ? '✅ PASS' : '❌ FAIL';
    const result = {
      test: testName,
      passed: passed,
      details: details,
      timestamp: new Date().toISOString()
    };
    
    this.results.push(result);
    console.log(`${status} ${testName}: ${details}`);
  }

  async generateReport() {
    const timestamp = new Date().toISOString().replace(/[:.]/g, '-');
    const reportDir = `/workspaces/stlouisintegration.com/testing/amisafe/results_${timestamp}`;
    
    if (!fs.existsSync(reportDir)) {
      fs.mkdirSync(reportDir, { recursive: true });
    }
    
    // Generate summary
    const totalTests = this.results.length;
    const passedTests = this.results.filter(r => r.passed).length;
    const failedTests = totalTests - passedTests;
    const successRate = ((passedTests / totalTests) * 100).toFixed(1);
    
    const summary = {
      summary: {
        total_tests: totalTests,
        passed: passedTests,
        failed: failedTests,
        success_rate: `${successRate}%`,
        test_date: new Date().toISOString()
      },
      results: this.results
    };
    
    // Write JSON report
    fs.writeFileSync(
      path.join(reportDir, 'hover_tooltip_test_results.json'),
      JSON.stringify(summary, null, 2)
    );
    
    // Write HTML report
    const htmlReport = `
<!DOCTYPE html>
<html>
<head>
    <title>Hover Tooltip Test Results</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .summary { background: #f8f9fa; padding: 15px; border-radius: 5px; margin-bottom: 20px; }
        .success { color: #28a745; }
        .failure { color: #dc3545; }
        .test-result { margin: 10px 0; padding: 10px; border-left: 4px solid #ddd; }
        .test-result.pass { border-left-color: #28a745; background: #f8fff9; }
        .test-result.fail { border-left-color: #dc3545; background: #fff8f8; }
    </style>
</head>
<body>
    <h1>AmISafe Hover Tooltip Test Results</h1>
    <div class="summary">
        <h2>Summary</h2>
        <p><strong>Total Tests:</strong> ${totalTests}</p>
        <p><strong>Passed:</strong> <span class="success">${passedTests}</span></p>
        <p><strong>Failed:</strong> <span class="failure">${failedTests}</span></p>
        <p><strong>Success Rate:</strong> ${successRate}%</p>
        <p><strong>Test Date:</strong> ${summary.summary.test_date}</p>
    </div>
    
    <h2>Test Results</h2>
    ${this.results.map(result => `
        <div class="test-result ${result.passed ? 'pass' : 'fail'}">
            <h3>${result.passed ? '✅' : '❌'} ${result.test}</h3>
            <p>${result.details}</p>
            <small>Timestamp: ${result.timestamp}</small>
        </div>
    `).join('')}
</body>
</html>`;
    
    fs.writeFileSync(path.join(reportDir, 'hover_tooltip_test_report.html'), htmlReport);
    
    console.log(`\n📊 Test Report Generated:`);
    console.log(`📁 Directory: ${reportDir}`);
    console.log(`📈 Success Rate: ${successRate}% (${passedTests}/${totalTests})`);
    
    return reportDir;
  }

  async cleanup() {
    if (this.browser) {
      await this.browser.close();
    }
  }

  async run() {
    try {
      await this.init();
      await this.testTooltipFunctionality();
      const reportDir = await this.generateReport();
      return reportDir;
    } catch (error) {
      console.error('❌ Test execution failed:', error.message);
      throw error;
    } finally {
      await this.cleanup();
    }
  }
}

// Run the test if called directly
if (require.main === module) {
  const tester = new TooltipTester();
  tester.run().then((reportDir) => {
    console.log(`\n🎉 Hover tooltip testing completed successfully!`);
    console.log(`📄 Report available at: ${reportDir}/hover_tooltip_test_report.html`);
    process.exit(0);
  }).catch((error) => {
    console.error('💥 Testing failed:', error.message);
    process.exit(1);
  });
}

module.exports = TooltipTester;