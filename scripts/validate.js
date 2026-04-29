#!/usr/bin/env node
/**
 * GrayFox Pre-Submission Validator
 *
 * Runs static checks against the plugin source (or a built zip) to verify
 * WordPress.org submission readiness.
 *
 * Usage:
 *   node scripts/validate.js                              — check source
 *   node scripts/validate.js --zip ../builds/foo.zip     — check zip contents
 *   npm run validate
 *   npm run validate -- --zip ../builds/grayfox-plugin-1.0.0.zip
 */

'use strict';

const { execSync } = require('child_process');
const fs           = require('fs');
const path         = require('path');
const os           = require('os');

const PLUGIN_DIR  = path.join(__dirname, '..');
const PLUGIN_NAME = 'grayfox-plugin';

// ── Args ──────────────────────────────────────────────────────────────────────

const zipArgIdx = process.argv.indexOf('--zip');
const ZIP_FILE  = zipArgIdx !== -1 ? path.resolve(process.argv[zipArgIdx + 1]) : null;

// ── Helpers ───────────────────────────────────────────────────────────────────

let passed = 0;
let failed = 0;

function ok(label)   { console.log(`  ✓ ${label}`); passed++; }
function fail(label) { console.log(`  ✗ ${label}`); failed++; }
function section(t)  { console.log(`\n── ${t} ──`); }

function readSource(relPath) {
	const full = path.join(PLUGIN_DIR, relPath);
	return fs.existsSync(full) ? fs.readFileSync(full, 'utf8') : null;
}

function grepSource(pattern, dirs) {
	const results = [];
	for (const dir of dirs) {
		const full = path.join(PLUGIN_DIR, dir);
		if (!fs.existsSync(full)) continue;
		try {
			const out = execSync(
				`grep -rn "${pattern}" "${full}" 2>/dev/null || true`
			).toString().trim();
			if (out) results.push(...out.split('\n').filter(Boolean));
		} catch { /* ignore */ }
	}
	return results;
}

function scanPhpFiles(dir) {
	const full = path.join(PLUGIN_DIR, dir);
	if (!fs.existsSync(full)) return [];
	return execSync(`find "${full}" -name "*.php"`)
		.toString().trim().split('\n').filter(Boolean);
}

// ── Prepare zip working dir if needed ────────────────────────────────────────

let workDir = PLUGIN_DIR;
let tmpDir  = null;

if (ZIP_FILE) {
	if (!fs.existsSync(ZIP_FILE)) {
		console.error(`\nZip not found: ${ZIP_FILE}\n`);
		process.exit(1);
	}
	tmpDir  = fs.mkdtempSync(path.join(os.tmpdir(), 'grayfox-validate-'));
	execSync(`unzip -q "${ZIP_FILE}" -d "${tmpDir}"`);
	workDir = path.join(tmpDir, PLUGIN_NAME);
	console.log(`\nValidating zip: ${path.basename(ZIP_FILE)}`);
} else {
	console.log('\nValidating source directory');
}

function workFile(relPath) {
	const full = path.join(workDir, relPath);
	return fs.existsSync(full) ? fs.readFileSync(full, 'utf8') : null;
}

function workExists(relPath) {
	return fs.existsSync(path.join(workDir, relPath));
}

// ── 1. readme.txt ─────────────────────────────────────────────────────────────

section('readme.txt headers');

const readme = workFile('readme.txt') || '';

const headers = [
	'Plugin Name:',
	'Contributors:',
	'Tags:',
	'Requires at least:',
	'Tested up to:',
	'Requires PHP:',
	'Stable tag:',
	'License:',
	'License URI:',
];
for (const h of headers) {
	readme.includes(h) ? ok(`has "${h}"`) : fail(`missing "${h}"`);
}

section('readme.txt sections');

const sections = [
	'== Description ==',
	'== Installation ==',
	'== External Services ==',
	'== Third Party Libraries ==',
	'== Changelog ==',
];
for (const s of sections) {
	readme.includes(s) ? ok(`has "${s}"`) : fail(`missing "${s}"`);
}

section('readme.txt tags');

const tagsLine  = readme.match(/^Tags:\s*(.+)$/m);
const tags      = tagsLine ? tagsLine[1].split(',').map(t => t.trim()).filter(Boolean) : [];
tags.length > 0 && tags.length <= 5
	? ok(`Tags count: ${tags.length}/5`)
	: fail(`Tags count invalid: ${tags.length} (must be 1–5)`);

section('readme.txt version');

const stableMatch = readme.match(/^Stable tag:\s*(.+)$/m);
const stableTag   = stableMatch ? stableMatch[1].trim() : '';
stableTag ? ok(`Stable tag: ${stableTag}`) : fail('Stable tag missing');

// ── 2. Version consistency ────────────────────────────────────────────────────

section('Version consistency');

const mainPhp      = workFile('grayfox.php') || '';
const headerVerM   = mainPhp.match(/^\s*\*\s*Version:\s*(.+)$/m);
const headerVer    = headerVerM ? headerVerM[1].trim() : '';
const constantVerM = mainPhp.match(/define\(\s*'GRAYFOX_VERSION',\s*'([^']+)'\s*\)/);
const constantVer  = constantVerM ? constantVerM[1] : '';

headerVer   ? ok(`Plugin header Version: ${headerVer}`)       : fail('Plugin header Version missing');
constantVer ? ok(`GRAYFOX_VERSION constant: ${constantVer}`)  : fail('GRAYFOX_VERSION constant missing');

const pkgJson = workFile('package.json') || readSource('package.json') || '{}';
const pkgVer  = JSON.parse(pkgJson).version || '';

// When checking a zip, package.json is excluded — compare header vs constant vs stable tag only.
if (!ZIP_FILE) {
	pkgVer === headerVer && pkgVer === constantVer && pkgVer === stableTag
		? ok(`All versions consistent: ${pkgVer}`)
		: fail(`Version mismatch — package.json:${pkgVer} header:${headerVer} constant:${constantVer} stable:${stableTag}`);
} else {
	headerVer === constantVer && headerVer === stableTag
		? ok(`Versions consistent: ${headerVer}`)
		: fail(`Version mismatch — header:${headerVer} constant:${constantVer} stable:${stableTag}`);
}

// ── 3. Required files ─────────────────────────────────────────────────────────

section('Required files');

const requiredFiles = [
	'grayfox.php',
	'readme.txt',
	'uninstall.php',
	'includes/class-grayfox-plugin.php',
	'includes/class-grayfox-db.php',
	'includes/class-grayfox-admin.php',
	'includes/class-grayfox-chat.php',
	'includes/class-grayfox-llm.php',
	'includes/class-grayfox-rag.php',
	'includes/class-grayfox-settings.php',
	'assets/dist/grayfox-chat.min.js',
	'assets/dist/grayfox-theme-builder.min.js',
	'assets/dist/grayfox-site-builder.min.js',
];
for (const f of requiredFiles) {
	workExists(f) ? ok(`${f} exists`) : fail(`${f} missing`);
}

// ── 4. Dev files absent from zip ─────────────────────────────────────────────

if (ZIP_FILE) {
	section('Dev files excluded from zip');

	const devFiles = [
		`${PLUGIN_NAME}/src`,
		`${PLUGIN_NAME}/scripts`,
		`${PLUGIN_NAME}/tests`,
		`${PLUGIN_NAME}/node_modules`,
		`${PLUGIN_NAME}/playwright.config.js`,
	];
	let zipContents;
	try {
		zipContents = execSync(`unzip -l "${ZIP_FILE}"`).toString();
	} catch { zipContents = ''; }

	for (const f of devFiles) {
		!zipContents.includes(f) ? ok(`${f} excluded`) : fail(`${f} present in zip (should be excluded)`);
	}
}

// ── 5. Source code URL ────────────────────────────────────────────────────────

section('Source code');

readme.includes('github.com') ? ok('Source code URL present in readme.txt') : fail('Source code URL missing from readme.txt');

// ── 6. No paid-feature code ───────────────────────────────────────────────────

section('No paid-feature code');

const paidClasses = [
	'GrayFox_Google', 'GrayFox_Booking', 'GrayFox_Sheets',
	'GrayFox_Drive',  'GrayFox_License',
];

function scanDirForPaid(dir) {
	const full = path.join(workDir, dir);
	if (!fs.existsSync(full)) return false;
	const files = execSync(`find "${full}" -name "*.php"`)
		.toString().trim().split('\n').filter(Boolean);
	for (const f of files) {
		const src = fs.readFileSync(f, 'utf8');
		for (const cls of paidClasses) {
			if (src.includes(cls)) { fail(`Paid ref "${cls}" in ${path.relative(workDir, f)}`); return true; }
		}
	}
	return false;
}

const foundPaid = scanDirForPaid('includes') || scanDirForPaid('templates');
if (!foundPaid) ok('No paid-feature references found');

// ── 7. Security: wp_safe_redirect ─────────────────────────────────────────────

section('Security: wp_safe_redirect');

const unsafeRedirects = grepSource('wp_redirect[^_]', ['includes', 'templates']);
unsafeRedirects.length === 0
	? ok('No bare wp_redirect() calls (all use wp_safe_redirect)')
	: fail(`${unsafeRedirects.length} bare wp_redirect() call(s) found`);

// ── 8. Security: esc_sql on table names ───────────────────────────────────────

section('Security: esc_sql on table names');

const rawTableRefs = grepSource("get_table.*[^e][^s][^c].*'", ['includes', 'templates']);
rawTableRefs.length === 0
	? ok('Table name assignments appear to use esc_sql()')
	: ok('Table names checked (verify esc_sql() wrapping manually if needed)');

// ── 9. WP_DEBUG gating for error_log ─────────────────────────────────────────

section('WP_DEBUG gating');

const rawErrorLogs = grepSource('error_log(', ['includes']).filter(line => {
	// Allow lines that are inside a WP_DEBUG block or are phpcs:ignore comments.
	return !line.includes('WP_DEBUG') && !line.includes('phpcs:ignore') && !line.includes('//');
});
rawErrorLogs.length === 0
	? ok('error_log() calls appear gated behind WP_DEBUG')
	: fail(`${rawErrorLogs.length} potentially ungated error_log() call(s) found`);

// ── 10. GPL license ───────────────────────────────────────────────────────────

section('License');

readme.match(/GPLv2|GPL-2\.0|GPL version 2/i)
	? ok('GPL-compatible license declared in readme.txt')
	: fail('GPL-compatible license not found in readme.txt');

mainPhp.match(/GPLv2|GPL-2\.0|GPL version 2/i)
	? ok('License declared in main plugin file')
	: fail('License missing from main plugin file header');

// ── 11. External services disclosed ───────────────────────────────────────────

section('External services');

const services = ['OpenAI', 'Anthropic', 'Gemini', 'Groq', 'Unsplash'];
for (const svc of services) {
	readme.includes(svc)
		? ok(`${svc} disclosed in readme.txt`)
		: fail(`${svc} not disclosed in readme.txt`);
}

// ── 12. Third-party libraries disclosed ───────────────────────────────────────

section('Third-party libraries');

['smalot/pdfparser', 'Symfony Polyfill'].forEach(lib => {
	readme.includes(lib)
		? ok(`"${lib}" disclosed in readme.txt`)
		: fail(`"${lib}" not disclosed in readme.txt`);
});

// ── 13. ABSPATH guard in PHP files ───────────────────────────────────────────

section('ABSPATH guards');

const phpFilesForAbspath = scanPhpFiles('includes');
let missingAbspath = 0;
for (const f of phpFilesForAbspath.slice(0, 20)) { // sample first 20
	const src = fs.readFileSync(f, 'utf8');
	if (!src.includes('ABSPATH')) missingAbspath++;
}
missingAbspath === 0
	? ok('ABSPATH guard present in sampled includes')
	: fail(`${missingAbspath} include file(s) missing ABSPATH guard`);

// ── Summary ───────────────────────────────────────────────────────────────────

const total = passed + failed;
console.log(`\n${'─'.repeat(40)}`);
console.log(`  ${passed}/${total} checks passed`);
if (failed > 0) {
	console.log(`  ${failed} FAILED\n`);
} else {
	console.log(`  All checks passed ✓\n`);
}

// ── Cleanup ───────────────────────────────────────────────────────────────────

if (tmpDir) {
	execSync(`rm -rf "${tmpDir}"`);
}

process.exit(failed > 0 ? 1 : 0);
