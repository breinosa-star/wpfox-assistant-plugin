'use strict';

/**
 * ChatHelper — abstracts all chat widget interactions for Playwright tests.
 *
 * Selectors map to the floating widget template (templates/chat-widget.php):
 *   #grayfox-chat-trigger  — the FAB button that opens the chat
 *   #grayfox-chat-window   — the chat window panel
 *   .grayfox-messages      — the scrollable message list
 *   #grayfox-input         — the textarea
 *   #grayfox-send          — the send button
 *   .grayfox-message--assistant .grayfox-bubble  — assistant message text
 *   .grayfox-message--user .grayfox-bubble       — user message text
 */
class ChatHelper {
	/**
	 * @param {import('@playwright/test').Page} page
	 * @param {number} responseTimeout  Max ms to wait for an assistant reply (default 90s).
	 */
	constructor(page, responseTimeout = 90_000) {
		this.page            = page;
		this.responseTimeout = responseTimeout;
		this.transcript      = [];   // { role, content, timestamp }
	}

	// ─── Widget lifecycle ────────────────────────────────────────────────────────

	/**
	 * Navigate to the site and open the chat widget.
	 * Returns the welcome message text.
	 */
	async open(path = '/') {
		await this.page.goto(path);
		await this.page.waitForLoadState('networkidle');

		// Open the widget.
		await this.page.click('#grayfox-chat-trigger');
		await this.page.waitForSelector('#grayfox-chat-window', { state: 'visible' });

		// Wait for the welcome message to appear.
		await this.page.waitForSelector('.grayfox-message--assistant', { timeout: 10_000 });
		const welcome = await this._getLastAssistantText();

		this.transcript.push({ role: 'assistant', content: welcome, timestamp: Date.now() });
		return welcome;
	}

	// ─── Messaging ───────────────────────────────────────────────────────────────

	/**
	 * Type and send a message. Does NOT wait for the reply.
	 * Waits for any previous response to finish first so isSending is false.
	 */
	async sendMessage(text) {
		// Ensure the input is enabled (previous response is done) before sending.
		await this.page.waitForSelector('#grayfox-input:not([disabled])', {
			timeout: this.responseTimeout,
		});

		// Record bubble count so waitForResponse() can collect only the new ones.
		this._bubbleCountBeforeSend = await this.page
			.locator('.grayfox-message--assistant .grayfox-bubble')
			.count();

		const input = this.page.locator('#grayfox-input');
		await input.fill(text);
		await this.page.click('#grayfox-send');
		this.transcript.push({ role: 'user', content: text, timestamp: Date.now() });
	}

	/**
	 * Wait for the assistant to finish replying and return the response text.
	 *
	 * Strategy: use the typing indicator as the signal.
	 * - TypingIndicator adds .grayfox-typing-indicator when request is in flight.
	 * - It is removed (detached) when the response finishes OR an error occurs.
	 * - This avoids the race condition of counting .grayfox-message--assistant
	 *   elements, since the typing indicator itself carries that class and inflates
	 *   the count while thinking.
	 */
	async waitForResponse() {
		// Wait for the input to be disabled — confirms the request is in flight.
		try {
			await this.page.waitForSelector('#grayfox-input[disabled]', { timeout: 5_000 });
		} catch {
			// Already disabled or extremely fast response.
		}

		// Wait for either:
		// (a) input re-enabled  — all bubbles rendered, normal or throttle-warning path.
		// (b) session blocked   — injection triggers MAX_STRIKES immediately; input stays
		//                         disabled and the placeholder is set to "Chat disabled."
		await Promise.race([
			this.page.waitForSelector('#grayfox-input:not([disabled])', {
				timeout: this.responseTimeout,
			}),
			this.page.waitForSelector('#grayfox-input[placeholder="Chat disabled."]', {
				timeout: this.responseTimeout,
			}),
		]);

		const text = await this._getNewBubblesText();
		this.transcript.push({ role: 'assistant', content: text, timestamp: Date.now() });
		return text;
	}

	/**
	 * Send a message and wait for the reply. Returns response text.
	 */
	async converse(text) {
		await this.sendMessage(text);
		return await this.waitForResponse();
	}

	// ─── Security / error detection ─────────────────────────────────────────────

	/**
	 * Check whether the last assistant message is a security warning.
	 * Returns { blocked: bool, warning: bool, text: string }.
	 */
	async getSecurityStatus() {
		const last = await this._getLastAssistantText();
		const isError = await this.page
			.locator('.grayfox-message--assistant:last-child .grayfox-bubble')
			.evaluate((el) => el.closest('.grayfox-message')?.classList.contains('grayfox-message--error') ?? false);

		return {
			blocked: last.includes('disconnected') || last.includes('Your activity has been logged'),
			warning: isError,
			text:    last,
		};
	}

	/**
	 * Check whether the input is currently disabled (session blocked or limit reached).
	 */
	async isInputDisabled() {
		return await this.page.locator('#grayfox-input').isDisabled();
	}

	// ─── Transcript ──────────────────────────────────────────────────────────────

	/**
	 * Return the full conversation transcript as an array.
	 * [{ role: 'user'|'assistant', content: string, timestamp: number }]
	 */
	getTranscript() {
		return this.transcript;
	}

	/**
	 * Return a human-readable conversation string for logging.
	 */
	formatTranscript(scenarioName = '') {
		const header = scenarioName
			? `=== ${scenarioName} ===\n`
			: '';
		return header + this.transcript
			.map((m) => `[${m.role.toUpperCase()}] ${m.content}`)
			.join('\n\n');
	}

	// ─── Private ─────────────────────────────────────────────────────────────────

	async _getLastAssistantText() {
		const bubbles = this.page.locator('.grayfox-message--assistant .grayfox-bubble');
		const count   = await bubbles.count();
		if (count === 0) return '';
		return (await bubbles.nth(count - 1).innerText()).trim();
	}

	async _getNewBubblesText() {
		const bubbles = this.page.locator('.grayfox-message--assistant .grayfox-bubble');
		const count   = await bubbles.count();
		const from    = this._bubbleCountBeforeSend ?? 0;
		const parts   = [];
		for (let i = from; i < count; i++) {
			const t = (await bubbles.nth(i).innerText()).trim();
			if (t) parts.push(t);
		}
		return parts.join(' ');
	}
}

module.exports = { ChatHelper };
