'use strict';

// @ts-check
const { test, expect } = require('@playwright/test');
const { execFileSync } = require('child_process');

// ── DB helpers ─────────────────────────────────────────────────────────────────

function mysqlExec(sql) {
	try {
		execFileSync(
			'docker',
			['exec', 'grayfox_db', 'mysql', '-uwordpress', '-pwordpress', 'wordpress', '-e', sql],
			{ stdio: 'pipe' },
		);
	} catch {
		// Docker unavailable — skip DB manipulation silently.
	}
}

function setVoiceEnabled(enabled) {
	mysqlExec(
		"INSERT INTO wp_options (option_name, option_value, autoload)" +
		" VALUES ('grayfox_voice_enabled', '" + (enabled ? '1' : '0') + "', 'yes')" +
		" ON DUPLICATE KEY UPDATE option_value = '" + (enabled ? '1' : '0') + "'",
	);
}

function clearVoiceRateLimitTransients() {
	mysqlExec(
		"DELETE FROM wp_options" +
		" WHERE option_name LIKE '_transient_grayfox_voice_rl_%'" +
		"    OR option_name LIKE '_transient_timeout_grayfox_voice_rl_%'",
	);
}

// ── Browser helpers ────────────────────────────────────────────────────────────

/**
 * Navigate to the homepage and return { restUrl, restNonce } from GrayFoxConfig.
 */
async function loadConfig(page) {
	await page.goto('/');
	await page.waitForLoadState('networkidle');
	return page.evaluate(() => ({
		restUrl:   window.GrayFoxConfig?.restUrl   ?? '',
		restNonce: window.GrayFoxConfig?.restNonce ?? '',
	}));
}

/**
 * POST to a voice endpoint from the browser context (carries session cookies).
 *
 * @param {import('@playwright/test').Page} page
 * @param {string} endpoint  Path relative to restUrl (e.g. '/voice/kb')
 * @param {Object} [body]
 * @param {string|null} [nonce]  Pass null to send the request without a nonce header.
 */
async function voicePost(page, endpoint, body = {}, nonce = undefined) {
	const config = await loadConfig(page);
	const useNonce = nonce !== undefined ? nonce : config.restNonce;

	return page.evaluate(
		({ url, body, nonce }) =>
			fetch(url, {
				method:  'POST',
				headers: {
					'Content-Type': 'application/json',
					...(nonce ? { 'X-WP-Nonce': nonce } : {}),
				},
				body: JSON.stringify(body),
			}).then(async (r) => ({ status: r.status, data: await r.json().catch(() => null) })),
		{ url: config.restUrl + endpoint, body, nonce: useNonce },
	);
}

// ─── Auth guard tests (no nonce required, voice state irrelevant) ─────────────

test.describe('Voice — auth guards', () => {
	test('VC-01: POST /voice/session without nonce returns 401', async ({ page }) => {
		const result = await voicePost(page, '/voice/session', {}, null);
		expect(result.status).toBe(401);
	});

	test('VC-02: POST /voice/kb without nonce returns 401', async ({ page }) => {
		const result = await voicePost(page, '/voice/kb', { query: 'hello' }, null);
		expect(result.status).toBe(401);
	});

	test('VC-03: POST /voice/lead without nonce returns 401', async ({ page }) => {
		const result = await voicePost(page, '/voice/lead', { email: 'test@example.com' }, null);
		expect(result.status).toBe(401);
	});
});

// ─── Feature guard tests (voice disabled) ────────────────────────────────────

test.describe('Voice — feature disabled guard', () => {
	test.beforeAll(() => setVoiceEnabled(false));

	test('VC-04: POST /voice/session with nonce but voice disabled returns 403', async ({ page }) => {
		const result = await voicePost(page, '/voice/session');
		expect(result.status).toBe(403);
	});

	test('VC-05: POST /voice/kb with nonce but voice disabled returns 403', async ({ page }) => {
		const result = await voicePost(page, '/voice/kb', { query: 'hello' });
		expect(result.status).toBe(403);
	});

	test('VC-06: POST /voice/lead with nonce but voice disabled returns 403', async ({ page }) => {
		const result = await voicePost(page, '/voice/lead', { email: 'test@example.com' });
		expect(result.status).toBe(403);
	});
});

// ─── Endpoint behaviour (voice enabled, HTTP environment) ─────────────────────

test.describe('Voice — endpoint behaviour', () => {
	test.beforeAll(() => {
		setVoiceEnabled(true);
		clearVoiceRateLimitTransients();
	});

	test.afterAll(() => {
		setVoiceEnabled(false);
		clearVoiceRateLimitTransients();
	});

	test('VC-07: POST /voice/session on HTTP returns 400 (HTTPS required)', async ({ page }) => {
		const result = await voicePost(page, '/voice/session');
		// HTTPS guard fires before OpenAI call — safe to test in Docker (HTTP).
		expect(result.status).toBe(400);
		expect(result.data?.message ?? '').toMatch(/https/i);
	});

	test('VC-08: POST /voice/kb returns 200 with documents array', async ({ page }) => {
		const result = await voicePost(page, '/voice/kb', { query: 'services' });
		expect(result.status).toBe(200);
		expect(Array.isArray(result.data?.documents)).toBe(true);
	});

	test('VC-09: POST /voice/kb with empty query returns 200 with documents array', async ({ page }) => {
		const result = await voicePost(page, '/voice/kb', { query: '' });
		expect(result.status).toBe(200);
		expect(Array.isArray(result.data?.documents)).toBe(true);
	});

	test('VC-10: POST /voice/lead with invalid email returns 400', async ({ page }) => {
		const result = await voicePost(page, '/voice/lead', {
			name:     'Test User',
			email:    'not-an-email',
			interest: 'pricing',
		});
		expect(result.status).toBe(400);
	});

	test('VC-11: POST /voice/lead with missing email returns 400', async ({ page }) => {
		const result = await voicePost(page, '/voice/lead', { name: 'No Email User' });
		expect(result.status).toBe(400);
	});

	test('VC-12: POST /voice/lead with valid email returns 200', async ({ page }) => {
		const result = await voicePost(page, '/voice/lead', {
			name:     'Test User',
			email:    'voice-test@example.com',
			interest: 'demo request',
		});
		expect(result.status).toBe(200);
		expect(result.data?.success).toBe(true);
	});
});

// ─── Widget UI ────────────────────────────────────────────────────────────────

test.describe('Voice — widget UI', () => {
	async function openWidget(page) {
		await page.goto('/');
		await page.waitForLoadState('networkidle');
		await page.click('#grayfox-chat-trigger');
		await page.waitForSelector('#grayfox-chat-window', { state: 'visible' });
	}

	test('VC-13: Mic button absent when voice is disabled', async ({ page }) => {
		setVoiceEnabled(false);
		await openWidget(page);
		await expect(page.locator('#grayfox-mic')).toHaveCount(0);
	});

	test('VC-14: Mic button absent on HTTP even when voice is enabled', async ({ page }) => {
		setVoiceEnabled(true);
		await openWidget(page);
		// is_ssl() returns false in the Docker HTTP environment.
		await expect(page.locator('#grayfox-mic')).toHaveCount(0);
		setVoiceEnabled(false);
	});
});
