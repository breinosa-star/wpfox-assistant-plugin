'use strict';

// @ts-check
const { defineConfig, devices } = require('@playwright/test');

const BASE_URL = process.env.BASE_URL || 'http://localhost:8080';

module.exports = defineConfig({
	globalSetup: './tests/e2e/global-setup.js',
	testDir:     './tests/e2e',
	testMatch:   '**/*.spec.js',
	timeout:     120_000,
	retries:     0,
	workers:     1,

	use: {
		baseURL:       BASE_URL,
		headless:      true,
		screenshot:    'only-on-failure',
		video:         'retain-on-failure',
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
