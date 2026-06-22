'use strict';

// @ts-check
const { defineConfig, devices } = require('@playwright/test');

/**
 * Remote config — runs Happy Path scenarios against the live deployment.
 * No Docker global setup; edge cases are excluded because they need DB access
 * to clear transients between tests and would block the caller's IP.
 */
module.exports = defineConfig({
	testDir:   './tests/e2e',
	testMatch: '**/*.spec.js',
	grep:      /Happy Path/,
	timeout:   120_000,
	retries:   1,
	workers:   1,

	use: {
		baseURL:    'https://dev.grayfoxdc.com',
		headless:   true,
		screenshot: 'only-on-failure',
		video:      'retain-on-failure',
	},

	projects: [
		{
			name: 'chromium',
			use:  { ...devices['Desktop Chrome'] },
		},
	],

	reporter: [
		['list'],
		['html', { outputFolder: 'tests/e2e/report', open: 'never' }],
	],

	outputDir: 'tests/e2e/results',
});
