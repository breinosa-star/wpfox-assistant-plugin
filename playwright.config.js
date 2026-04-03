// @ts-check
const { defineConfig, devices } = require('@playwright/test');

module.exports = defineConfig({
	testDir: './tests/e2e',
	outputDir: './tests/e2e/results',
	globalSetup: './tests/e2e/global-setup.js',

	// Each test gets up to 3 minutes — LLM responses can be slow.
	timeout: 180_000,
	expect: { timeout: 10_000 },

	// Run tests sequentially — one conversation at a time to avoid hammering the LLM.
	workers: 1,
	fullyParallel: false,

	reporter: [
		['list'],
		['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
	],

	use: {
		baseURL: 'http://localhost:8081',
		headless: true,
		// Keep viewport consistent.
		viewport: { width: 1280, height: 800 },
		// Capture screenshot and trace on failure.
		screenshot: 'only-on-failure',
		trace: 'retain-on-failure',
		// Extra time for actions (typing, clicking) to settle.
		actionTimeout: 15_000,
	},

	projects: [
		{
			name: 'chromium',
			use: { ...devices['Desktop Chrome'] },
		},
	],
});
