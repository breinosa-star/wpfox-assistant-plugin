'use strict';

/**
 * Playwright global setup — runs once before the entire test suite.
 *
 * Raises the GrayFox IP rate limits well above the number of test scenarios
 * so that the IP rate limiter never fires during a test run.
 * Also clears any leftover IP-session transients from previous runs.
 *
 * Uses docker exec against the named MySQL container (grayfox_db) so no
 * docker-compose profile or WP-CLI container is needed.
 */

const { execFileSync } = require('child_process');

/**
 * Run a SQL statement inside the grayfox_db container.
 * @param {string} sql
 */
function mysqlExec(sql) {
	execFileSync(
		'docker',
		['exec', 'grayfox_db', 'mysql', '-uwordpress', '-pwordpress', 'wordpress', '-e', sql],
		{ stdio: 'pipe' },
	);
}

module.exports = async function globalSetup() {
	// Raise per-IP rate limits so the full scenario set never hits the cap.
	// Uses INSERT … ON DUPLICATE KEY UPDATE to handle the case where the
	// options row doesn't exist yet (i.e. defaults were never saved to DB).
	mysqlExec(
		"INSERT INTO wp_options (option_name, option_value, autoload)" +
		" VALUES ('grayfox_ip_sessions_per_hour', '999', 'yes')" +
		" ON DUPLICATE KEY UPDATE option_value = '999'",
	);
	mysqlExec(
		"INSERT INTO wp_options (option_name, option_value, autoload)" +
		" VALUES ('grayfox_ip_sessions_per_day', '999', 'yes')" +
		" ON DUPLICATE KEY UPDATE option_value = '999'",
	);

	// Delete all security-related transients left over from previous test runs:
	// - IP rate-limit counters (hourly + daily)
	// - IP blocks set by the security classifier
	// - Strike counters (per-session and per-IP surrogate keys)
	// Leaving these in place causes tests to fail with 429 even when limits
	// are set high, because the security layer checks them independently.
	mysqlExec(
		"DELETE FROM wp_options" +
		" WHERE option_name LIKE '_transient_grayfox_ip_%'" +
		"    OR option_name LIKE '_transient_timeout_grayfox_ip_%'" +
		"    OR option_name LIKE '_transient_grayfox_strikes_%'" +
		"    OR option_name LIKE '_transient_timeout_grayfox_strikes_%'",
	);

	console.log('[global-setup] IP rate limits set to 999; security transients cleared.');
};
