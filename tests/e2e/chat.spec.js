'use strict';

// @ts-check
const { test, expect }  = require('@playwright/test');
const { execFileSync }  = require('child_process');
const fs                = require('fs');
const path              = require('path');
const { ChatHelper }    = require('./helpers/ChatHelper');
const happyPaths        = require('./scenarios/happy-path');
const edgeCases         = require('./scenarios/edge-cases');

/**
 * Clear per-IP security transients (blocks + strikes) between edge-case tests.
 * Security scenarios that intentionally trigger blocks would otherwise poison
 * subsequent tests running from the same IP address.
 */
function clearSecurityTransients() {
	try {
		execFileSync('docker', [
			'exec', 'grayfox_db',
			'mysql', '-uwordpress', '-pwordpress', 'wordpress', '-e',
			"DELETE FROM wp_options" +
			" WHERE option_name LIKE '_transient_grayfox_ip_block_%'" +
			"    OR option_name LIKE '_transient_timeout_grayfox_ip_block_%'" +
			"    OR option_name LIKE '_transient_grayfox_strikes_%'" +
			"    OR option_name LIKE '_transient_timeout_grayfox_strikes_%'",
		], { stdio: 'pipe' });
	} catch {
		// Docker not available (e.g. remote run) — skip transient clearing.
	}
}

const TRANSCRIPTS_DIR = path.join(__dirname, 'transcripts');
fs.mkdirSync(TRANSCRIPTS_DIR, { recursive: true });

// ── Helpers ────────────────────────────────────────────────────────────────────

/**
 * Save conversation transcript to a timestamped file.
 */
function saveTranscript(scenario, chat) {
	const ts       = new Date().toISOString().replace(/[:.]/g, '-');
	const filename = `${ts}_${scenario.id}.txt`;
	const filePath = path.join(TRANSCRIPTS_DIR, filename);

	const header = [
		`ID:       ${scenario.id}`,
		`Name:     ${scenario.name}`,
		`Persona:  ${scenario.persona}`,
		`Type:     ${scenario.type}`,
		scenario.category ? `Category: ${scenario.category}` : '',
		scenario.risk     ? `Risk:     ${scenario.risk}` : '',
		'─'.repeat(60),
		'',
	].filter(Boolean).join('\n');

	fs.writeFileSync(filePath, header + chat.formatTranscript() + '\n');
	return filePath;
}

// ── Test factory ──────────────────────────────────────────────────────────────

function runScenario(scenario) {
	test(scenario.name, async ({ page }) => {
		const chat = new ChatHelper(page);

		// 1. Open the widget and capture the welcome message.
		const welcome = await chat.open('/');
		expect(welcome.length).toBeGreaterThan(0);

		// 2. Play through each user message in the scenario.
		const responses = [];
		for (const message of scenario.messages) {
			const reply = await chat.converse(message);
			responses.push(reply);

			// Hard stop: if the session was blocked (security), stop sending.
			const status = await chat.getSecurityStatus();
			if (status.blocked) {
				break;
			}
		}

		// 3. Save full transcript regardless of pass/fail.
		const filePath = saveTranscript(scenario, chat);
		console.log(`  → transcript: ${path.relative(process.cwd(), filePath)}`);

		// ── Assertions ─────────────────────────────────────────────────────────

		const allResponses   = responses.join('\n').toLowerCase();
		const lastResponse   = (responses[responses.length - 1] || '').toLowerCase();
		const securityStatus = await chat.getSecurityStatus();
		const fullText       = chat.formatTranscript().toLowerCase();

		// noErrors: no hard error messages in any response.
		if (scenario.expect?.noErrors) {
			for (const r of responses) {
				expect(r, 'Response should not be a hard error').not.toMatch(
					/lLM error occurred|ai assistant is not configured|something went wrong/i
				);
			}
		}

		// noSecurityWarning: classifier should not flag legitimate messages.
		if (scenario.expect?.noSecurityWarning) {
			expect(securityStatus.warning, 'Should not trigger security warning').toBe(false);
		}

		// securityWarning: classifier SHOULD flag this message.
		if (scenario.expect?.securityWarning) {
			expect(securityStatus.warning || securityStatus.blocked,
				'Should trigger security warning or block'
			).toBe(true);
		}

		// sessionBlocked: repeated violations should block the session.
		if (scenario.expect?.sessionBlocked) {
			expect(securityStatus.blocked, 'Session should be blocked after repeated violations').toBe(true);
		}

		// mentionsBooking: at some point the assistant should mention appointment booking.
		if (scenario.expect?.mentionsBooking) {
			expect(allResponses, 'Should mention booking/appointments').toMatch(/book|appointment|schedul/i);
		}

		// mentionsTrial: assistant should surface the Trial plan.
		if (scenario.expect?.mentionsTrial) {
			expect(allResponses, 'Should mention Trial plan').toMatch(/trial/i);
		}

		// mentionsPro: assistant should surface the Pro plan.
		if (scenario.expect?.mentionsPro) {
			expect(allResponses, 'Should mention Pro plan').toMatch(/pro/i);
		}

		// mentionsDataOwnership: assistant should confirm the customer owns their data.
		if (scenario.expect?.mentionsDataOwnership) {
			expect(allResponses, 'Should confirm data ownership').toMatch(/own|your data|your server|you own/i);
		}

		// mentionsDriveSync: assistant should mention Google Drive auto-sync.
		if (scenario.expect?.mentionsDriveSync) {
			expect(allResponses, 'Should mention Google Drive sync').toMatch(/drive|sync/i);
		}

		// mentionsDocumentUpload: assistant should mention uploading documents.
		if (scenario.expect?.mentionsDocumentUpload) {
			expect(allResponses, 'Should mention document upload').toMatch(/document|upload|pdf|file/i);
		}
	});
}

// ── Test suites ───────────────────────────────────────────────────────────────

test.describe('Happy Path', () => {
	for (const scenario of happyPaths) {
		runScenario(scenario);
	}
});

test.describe('Edge Cases', () => {
	// Each security scenario can block the test IP for up to 1 hour.
	// Clear blocks + strikes before every test so scenarios are isolated.
	test.beforeEach(clearSecurityTransients);

	for (const scenario of edgeCases) {
		runScenario(scenario);
	}
});
