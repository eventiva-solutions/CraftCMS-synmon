#!/usr/bin/env node
'use strict';

const { chromium } = require('playwright');

async function main() {
    let inputData = '';

    // Read stdin
    await new Promise((resolve) => {
        process.stdin.setEncoding('utf8');
        process.stdin.on('data', (chunk) => { inputData += chunk; });
        process.stdin.on('end', resolve);
    });

    let payload;
    try {
        payload = JSON.parse(inputData);
    } catch (e) {
        output({ success: false, error: 'Invalid JSON input: ' + e.message });
        return;
    }

    const steps         = payload.steps || [];
    const globalTimeout = (payload.globalTimeout || 120) * 1000;

    const startTime = Date.now();
    const stepResults = [];

    let browser = null;
    let page    = null;

    try {
        browser = await chromium.launch({ headless: true });
        const context = await browser.newContext({
            ignoreHTTPSErrors: true,
        });
        page = await context.newPage();

        // Collect console logs per step
        const consoleLogs = [];
        page.on('console', (msg) => {
            consoleLogs.push('[' + msg.type() + '] ' + msg.text());
        });

        for (const step of steps) {
            const stepStart  = Date.now();
            let   stepStatus = 'pass';
            let   errorMsg   = null;
            let   stepLogs   = [];

            const timeout = step.timeout || 30000;

            try {
                await runStep(page, step, timeout);
                stepStatus = 'pass';
            } catch (e) {
                stepStatus = 'fail';
                errorMsg   = e.message;
            }

            stepLogs = [...consoleLogs];
            consoleLogs.length = 0;

            stepResults.push({
                sortOrder:     step.sortOrder,
                status:        stepStatus,
                durationMs:    Date.now() - stepStart,
                errorMessage:  errorMsg,
                consoleOutput: stepLogs.join('\n') || null,
            });

            if (stepStatus === 'fail') {
                break; // Stop on first failure
            }

            // Check global timeout
            if ((Date.now() - startTime) >= globalTimeout) {
                stepResults.push({
                    sortOrder:    (step.sortOrder || 0) + 1,
                    status:       'fail',
                    durationMs:   0,
                    errorMessage: 'Global timeout exceeded',
                    consoleOutput: null,
                });
                break;
            }
        }

        await browser.close();

        const allPassed = stepResults.every(s => s.status === 'pass');
        const failedStep = stepResults.find(s => s.status === 'fail');

        // Get versions
        const nodeVersion       = process.version;
        const playwrightPkg     = require('./node_modules/playwright/package.json');
        const playwrightVersion = playwrightPkg.version || 'unknown';

        output({
            success:           allPassed,
            durationMs:        Date.now() - startTime,
            nodeVersion:       nodeVersion,
            playwrightVersion: playwrightVersion,
            failedStep:        failedStep ? failedStep.sortOrder : null,
            steps:             stepResults,
        });

    } catch (e) {
        if (browser) await browser.close().catch(() => {});
        output({
            success:    false,
            durationMs: Date.now() - startTime,
            error:      e.message,
            steps:      stepResults,
        });
    }
}

async function runStep(page, step, timeout) {
    switch (step.type) {
        case 'navigate':
            await page.goto(step.value, { waitUntil: 'networkidle', timeout });
            break;

        case 'click':
            await page.click(step.selector, { timeout });
            break;

        case 'fill':
            await page.fill(step.selector, step.value || '', { timeout });
            break;

        case 'select':
            await page.selectOption(step.selector, step.value || '', { timeout });
            break;

        case 'pressKey':
            await page.keyboard.press(step.value || 'Enter');
            break;

        case 'assertVisible':
            await page.waitForSelector(step.selector, { state: 'visible', timeout });
            break;

        case 'assertText': {
            const el = await page.waitForSelector(step.selector, { timeout });
            const text = await el.textContent();
            if (!text.includes(step.value || '')) {
                throw new Error(`assertText: expected "${step.value}" but got "${text}"`);
            }
            break;
        }

        case 'assertUrl': {
            const url = page.url();
            if (!url.includes(step.value || '')) {
                throw new Error(`assertUrl: expected URL to contain "${step.value}" but got "${url}"`);
            }
            break;
        }

        case 'assertTitle': {
            const title = await page.title();
            if (!title.includes(step.value || '')) {
                throw new Error(`assertTitle: expected title to contain "${step.value}" but got "${title}"`);
            }
            break;
        }

        case 'waitForSelector':
            await page.waitForSelector(step.selector, { timeout });
            break;

        default:
            throw new Error(`Unknown step type: ${step.type}`);
    }
}

function output(data) {
    process.stdout.write(JSON.stringify(data) + '\n');
}

main().catch((e) => {
    output({ success: false, error: 'Unhandled error: ' + e.message });
    process.exit(1);
});
