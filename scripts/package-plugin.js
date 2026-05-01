#!/usr/bin/env node
/**
 * GrayFox Plugin Packager
 *
 * Validates the plugin then packages it into a distributable zip.
 * Output: ../builds/kbfox-{version}.zip
 *
 * Usage:
 *   node scripts/package-plugin.js           — build zip
 *   node scripts/package-plugin.js --dry-run — validate only, no zip
 *   npm run package
 *   npm run package:dry
 *
 *  - Validates readme.txt headers and required sections
 *  - Validates version consistency across all version markers
 *  - Checks readme.txt tag count (WP.org max: 5)
 *  - Verifies required files exist
 *  - Scans PHP source for paid-feature class references
 *  - Explicitly excludes all dev infrastructure + paid feature files
 */

'use strict';

const { execSync } = require('child_process');
const fs           = require('fs');
const path         = require('path');

const DRY_RUN     = process.argv.includes('--dry-run');
const PLUGIN_DIR  = path.join(__dirname, '..');
const PLUGIN_NAME = 'kbfox';
const BUILDS_DIR  = path.join(PLUGIN_DIR, '..', 'builds');

const pkg     = JSON.parse(fs.readFileSync(path.join(PLUGIN_DIR, 'package.json'), 'utf8'));
const VERSION = pkg.version;
const outFile = path.join(BUILDS_DIR, `${PLUGIN_NAME}-${VERSION}.zip`);

let errors = 0;

function pass(msg)  { console.log(`  ✓ ${msg}`); }
function fail(msg)  { console.log(`  ✗ ${msg}`); errors++; }
function section(t) { console.log(`\n── ${t} ──`); }

// ── Header ────────────────────────────────────────────────────────────────────

console.log(`\nGrayFox Plugin Packager v${VERSION}`);
console.log('Packages: knowledge-base, chatbot');

// ── Validate readme.txt ───────────────────────────────────────────────────────

section('Validating readme.txt');

const readmePath = path.join(PLUGIN_DIR, 'readme.txt');
const readme     = fs.existsSync(readmePath) ? fs.readFileSync(readmePath, 'utf8') : '';

const requiredHeaders = [
	'Plugin Name:',
	'Contributors:',
	'Requires at least:',
	'Tested up to:',
	'Requires PHP:',
	'Stable tag:',
	'License:',
];

for (const h of requiredHeaders) {
	if (readme.includes(h)) pass(`readme.txt has "${h}"`);
	else                     fail(`readme.txt missing "${h}"`);
}

const requiredSections = [
	'== Description ==',
	'== External Services ==',
	'== Installation ==',
	'== Changelog ==',
];

for (const s of requiredSections) {
	if (readme.includes(s)) pass(`readme.txt has section "${s}"`);
	else                     fail(`readme.txt missing section "${s}"`);
}

const stableMatch = readme.match(/^Stable tag:\s*(.+)$/m);
const stableTag   = stableMatch ? stableMatch[1].trim() : null;
if (stableTag) pass(`readme.txt Stable tag: ${stableTag}`);
else           fail('readme.txt Stable tag not found');

// ── Version consistency ───────────────────────────────────────────────────────

section('Validating version consistency');

const mainPlugin   = fs.readFileSync(path.join(PLUGIN_DIR, 'kbfox.php'), 'utf8');
const versionMatch = mainPlugin.match(/^\s*\*\s*Version:\s*(.+)$/m);
const pluginVer    = versionMatch ? versionMatch[1].trim() : null;

if (pluginVer) pass(`Plugin file Version: ${pluginVer}`);
else           fail('Plugin file Version header not found');

const headersMatch  = pluginVer === VERSION && stableTag === VERSION;
if (headersMatch) pass('Version headers consistent');
else              fail(`Version mismatch: package.json=${VERSION}, plugin header=${pluginVer}, stable tag=${stableTag}`);

const constantMatch = mainPlugin.match(/define\(\s*'GRAYFOX_VERSION',\s*'([^']+)'\s*\)/);
const constantVer   = constantMatch ? constantMatch[1] : null;
if (constantVer === VERSION) pass(`GRAYFOX_VERSION constant: ${constantVer}`);
else                         fail(`GRAYFOX_VERSION mismatch: ${constantVer} (expected ${VERSION})`);

// ── readme.txt tags ───────────────────────────────────────────────────────────

section('Checking readme.txt tags');

const tagsMatch = readme.match(/^Tags:\s*(.+)$/m);
if (tagsMatch) {
	const tags = tagsMatch[1].split(',').map(t => t.trim()).filter(Boolean);
	if (tags.length <= 5) pass(`Tags: ${tags.length}/5 used (${tags.join(', ')})`);
	else                  fail(`Too many tags: ${tags.length}/5 (WP.org max is 5)`);
} else {
	fail('Tags line not found in readme.txt');
}

// ── Required files ────────────────────────────────────────────────────────────

section('Checking required files');

const required = [ 'uninstall.php', 'kbfox.php', 'readme.txt' ];
for (const f of required) {
	if (fs.existsSync(path.join(PLUGIN_DIR, f))) pass(`${f} exists`);
	else                                          fail(`${f} missing`);
}

// ── Paid-feature scan ─────────────────────────────────────────────────────────

section('Scanning for paid-feature code');

const PAID_CLASSES = [
	'GrayFox_ThemeBuilder',
	'GrayFox_SiteBuilder',
	'GrayFox_Audit',
	'GrayFox_Admin_Pro',
	'GrayFox_Pro_Plugin',
	'class-grayfox-theme-builder',
	'class-grayfox-site-builder',
	'class-grayfox-audit',
	'class-grayfox-admin-pro',
	'GrayFox_Google',
	'GrayFox_Booking',
	'GrayFox_Sheets',
	'GrayFox_Drive',
	'GrayFox_License',
	'class-grayfox-google',
	'class-grayfox-booking',
	'class-grayfox-sheets',
	'class-grayfox-drive',
	'class-grayfox-license',
];

function scanDir(dir) {
	if (!fs.existsSync(dir)) return [];
	return fs.readdirSync(dir, { withFileTypes: true }).flatMap(entry => {
		const full = path.join(dir, entry.name);
		if (entry.isDirectory()) return scanDir(full);
		if (entry.name.endsWith('.php')) return [ full ];
		return [];
	});
}

const phpFiles = [
	...scanDir(path.join(PLUGIN_DIR, 'includes')),
	...scanDir(path.join(PLUGIN_DIR, 'templates')),
	path.join(PLUGIN_DIR, 'kbfox.php'),
];

let paidFound = false;
for (const file of phpFiles) {
	const src = fs.readFileSync(file, 'utf8');
	for (const cls of PAID_CLASSES) {
		if (src.includes(cls)) {
			fail(`Paid-feature reference "${cls}" found in ${path.relative(PLUGIN_DIR, file)}`);
			paidFound = true;
		}
	}
}
if (!paidFound) pass('No paid-feature references in PHP source');

// ── Abort on errors ───────────────────────────────────────────────────────────

if (errors > 0) {
	console.log(`\n✗ ${errors} validation error(s). Fix before packaging.\n`);
	process.exit(1);
}

if (DRY_RUN) {
	console.log('\n✓ Dry run complete — all checks passed.\n');
	process.exit(0);
}

// ── Build zip ─────────────────────────────────────────────────────────────────

section('Building zip');

// Always-excluded paths (dev infrastructure).
const ALWAYS_EXCLUDE = [
	// Dev infrastructure.
	'node_modules',
	'src',
	'scripts',
	'prompts',
	'tests',
	'docs',
	'bin',
	'.git',
	'.gitignore',
	'.idea',
	'package.json',
	'package-lock.json',
	'composer.lock',
	'docker-compose.yml',
	'phpunit.xml',
	// Root-level markdown files.
	'*.md',
	// E2E test config.
	'playwright.config.js',
	// Vendor dev artifacts (pdfparser).
	'vendor/smalot/pdfparser/Makefile',
	'vendor/smalot/pdfparser/.php-cs-fixer.php',
	'vendor/smalot/pdfparser/doc',
	'vendor/smalot/pdfparser/phpunit-windows.xml',
	'vendor/smalot/pdfparser/alt_autoload.php-dist',
	// macOS artifacts.
	'._*',
];

fs.mkdirSync(BUILDS_DIR, { recursive: true });

if (fs.existsSync(outFile)) {
	fs.unlinkSync(outFile);
	console.log(`  Removed previous: ${path.basename(outFile)}`);
}

const parentDir   = path.dirname(PLUGIN_DIR);
const excludeArgs = ALWAYS_EXCLUDE
	.map(e => {
		if (e.startsWith('*.') || e.startsWith('._')) {
			return `-x "${PLUGIN_NAME}/${e}"`;
		}
		return `--exclude "${PLUGIN_NAME}/${e}/*" --exclude "${PLUGIN_NAME}/${e}"`;
	})
	.join(' ');

const cmd = [
	`cd "${parentDir}" &&`,
	`zip -r "${outFile}" "${PLUGIN_NAME}/"`,
	excludeArgs,
	`-x "*.DS_Store"`,
	`-x "*/._*"`,
	`-x "*/.github/*"`,
].join(' ');

try {
	execSync(cmd, { stdio: 'inherit' });
} catch (e) {
	console.error('\nzip command failed.');
	process.exit(1);
}

const bytes = fs.statSync(outFile).size;
const kb    = (bytes / 1024).toFixed(1);
const count = parseInt(
	execSync(`unzip -l "${outFile}" | tail -1`).toString().trim().split(/\s+/)[0],
	10
);

console.log(`\n✓ Done: builds/${PLUGIN_NAME}-${VERSION}.zip`);
console.log(`  Size:  ${kb} KB`);
console.log(`  Files: ${count}\n`);
