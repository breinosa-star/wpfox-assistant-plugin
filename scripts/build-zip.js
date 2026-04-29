#!/usr/bin/env node
/**
 * Package the plugin into a distributable zip.
 *
 * Output: ../builds/grayfox-plugin-{version}.zip
 *
 * Excludes all development files — only production assets are included.
 * Requires the system `zip` command (available on macOS and Linux).
 *
 * Usage:
 *   node scripts/build-zip.js
 *   npm run build:zip
 */

'use strict';

const { execSync } = require('child_process');
const fs           = require('fs');
const path         = require('path');

const PLUGIN_DIR = path.join(__dirname, '..');
const BUILDS_DIR = path.join(PLUGIN_DIR, '..', 'builds');
const PLUGIN_NAME = 'grayfox-plugin';

// Read version from package.json.
const pkg     = JSON.parse(fs.readFileSync(path.join(PLUGIN_DIR, 'package.json'), 'utf8'));
const version = pkg.version;
const outFile = path.join(BUILDS_DIR, `${PLUGIN_NAME}-${version}.zip`);

// Paths relative to the plugin directory, excluded from the zip.
const EXCLUDES = [
	'node_modules',
	'src',
	'scripts',
	'prompts',
	'tests',
	'docs',
	'.git',
	'.gitignore',
	'package.json',
	'package-lock.json',
	'composer.json',
	'composer.lock',
	'docker-compose.yml',
	'phpunit.xml',
	'code-issues.md',
	'code-reviewer.md',
	'developer.md',
	'manual-test-checklist.md',
	'qa-report.md',
	'qa-tester.md',
	'.idea',
	'.vscode',
	'.sops.yaml',
	'.env',
	'.env.enc',
];

// Ensure builds directory exists.
fs.mkdirSync(BUILDS_DIR, { recursive: true });

// Remove previous zip for this version if it exists.
if (fs.existsSync(outFile)) {
	fs.unlinkSync(outFile);
	console.log(`Removed previous: ${path.basename(outFile)}`);
}

// Build the zip command.
// We cd to the parent of the plugin directory and zip the plugin folder,
// so the zip extracts as grayfox-plugin/ (standard WP plugin structure).
const parentDir   = path.dirname(PLUGIN_DIR);
const excludeArgs = EXCLUDES
	.map(e => `--exclude "${PLUGIN_NAME}/${e}/*" --exclude "${PLUGIN_NAME}/${e}"`)
	.join(' ');

const cmd = `cd "${parentDir}" && zip -r "${outFile}" "${PLUGIN_NAME}/" ${excludeArgs} -x "*.DS_Store" -x "*/._*" -x "*/.github/*"`;

console.log(`Building v${version}...`);

try {
	execSync(cmd, { stdio: 'inherit' });
} catch (e) {
	console.error('zip failed.');
	process.exit(1);
}

// Report output size.
const bytes = fs.statSync(outFile).size;
const kb    = (bytes / 1024).toFixed(1);
console.log(`\nDone: builds/${PLUGIN_NAME}-${version}.zip (${kb} KB)`);
