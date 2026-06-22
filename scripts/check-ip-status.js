#!/usr/bin/env node
/**
 * Check whether the test-runner IP is blocked on a deployed site.
 *
 * Opens the chat widget, sends a single "hello", and classifies
 * the response as: blocked | rate-limited | error | ok
 *
 * Usage:
 *   node scripts/check-ip-status.js
 *   node scripts/check-ip-status.js https://dev.grayfoxdc.com
 */

'use strict';

const { chromium } = require('playwright');

const TARGET = process.argv[2] || 'https://dev.grayfoxdc.com';

(async () => {
	console.log(`\nChecking IP status against: ${TARGET}\n`);

	const browser = await chromium.launch({ headless: true });
	const page    = await browser.newPage();

	// Intercept the AJAX chat response to read the raw server payload.
	let ajaxResponse = null;
	page.on('response', async (res) => {
		if (res.url().includes('admin-ajax.php') && ajaxResponse === null) {
			try {
				const body = await res.json();
				ajaxResponse = { status: res.status(), body };
			} catch { /* ignore non-JSON */ }
		}
	});

	try {
		await page.goto(TARGET, { waitUntil: 'networkidle', timeout: 30_000 });

		// Open chat widget.
		const trigger = page.locator('#grayfox-chat-trigger');
		if (!(await trigger.isVisible({ timeout: 8_000 }))) {
			console.log('✗  Chat widget not found on page — is the plugin active?');
			process.exit(1);
		}
		await trigger.click();
		await page.waitForSelector('#grayfox-chat-window', { state: 'visible', timeout: 8_000 });

		// Wait for welcome message.
		await page.waitForSelector('.grayfox-message--assistant', { timeout: 10_000 });

		// Send a benign message.
		await page.waitForSelector('#grayfox-input:not([disabled])', { timeout: 10_000 });
		await page.locator('#grayfox-input').fill('hello');
		await page.click('#grayfox-send');

		// Wait for response (typing indicator → gone).
		try {
			await page.waitForSelector('.grayfox-typing-indicator', { state: 'attached', timeout: 6_000 });
		} catch { /* already gone */ }
		await page.waitForSelector('.grayfox-typing-indicator', { state: 'detached', timeout: 60_000 });

		// Read the last assistant message.
		const bubbles  = page.locator('.grayfox-message--assistant .grayfox-bubble');
		const count    = await bubbles.count();
		const lastText = count > 0 ? (await bubbles.nth(count - 1).innerText()).trim() : '';

		// Is input disabled now?
		const inputDisabled = await page.locator('#grayfox-input').isDisabled();

		// Classify using the raw AJAX payload first (more reliable than bubble text).
		if (ajaxResponse) {
			const { status, body } = ajaxResponse;
			const data = body.data || {};

			if (data.security === 'blocked') {
				console.log('🔴  BLOCKED — security system blocked this IP/session.');
				console.log(`    Server message: "${data.message}"`);
			} else if (data.rate_limited) {
				console.log('🟡  RATE LIMITED — IP has exceeded the session-per-hour or session-per-day limit.');
				console.log(`    Server message: "${data.message}"`);
				console.log('    ℹ  Transients reset hourly. Wait ~1h or clear via WP admin to run tests again.');
			} else if (data.security === 'warning') {
				console.log('🟠  SECURITY WARNING — message flagged but not blocked yet.');
				console.log(`    Strikes: ${data.strikes}   Server message: "${data.message}"`);
			} else if (status === 503 || data.message?.includes('not configured')) {
				console.log('🟠  CONFIG ERROR — AI provider is not configured on this site.');
				console.log(`    Server message: "${data.message}"`);
			} else if (!body.success) {
				console.log(`🟠  SERVER ERROR (HTTP ${status}) — unexpected error response.`);
				console.log(`    Raw: ${JSON.stringify(data)}`);
			} else {
				console.log('🟢  OK — IP is not blocked or rate-limited. Chat responded normally.');
				console.log(`    Response: "${lastText.slice(0, 120)}${lastText.length > 120 ? '…' : ''}"`);
			}
		} else {
			// Fallback: classify from bubble text if AJAX response wasn't captured.
			const lower = lastText.toLowerCase();
			if (lower.includes('disconnected') || lower.includes('activity has been logged')) {
				console.log('🔴  BLOCKED');
			} else if (lower.includes('too many') || lower.includes('try again later')) {
				console.log('🟡  RATE LIMITED');
			} else if (lower.includes('something went wrong') || lower.includes('not configured')) {
				console.log('🟠  ERROR (could not capture raw server response)');
			} else {
				console.log('🟢  OK');
			}
			console.log(`    Bubble text: "${lastText}"`);
		}

		if (inputDisabled) {
			console.log('    ⚠  Input is disabled after this message.');
		}

	} catch (err) {
		console.error('Error during check:', err.message);
		process.exit(1);
	} finally {
		await browser.close();
	}

	console.log();
})();
