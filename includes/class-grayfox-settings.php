<?php
/**
 * Admin settings page (WordPress Settings API).
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_Settings
 *
 * Registers the GrayFox settings admin subpage and handles option sanitization.
 */
class GrayFox_Settings {

	/**
	 * Option group name.
	 *
	 * @var string
	 */
	const OPTION_GROUP = 'grayfox_options';

	/**
	 * Settings page slug.
	 *
	 * @var string
	 */
	const PAGE_SLUG = 'grayfox-settings';

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( 'admin_init', $this, 'register_settings' );
		$loader->add_action( 'wp_ajax_grayfox_test_llm', $this, 'ajax_test_llm' );
	}

	/**
	 * Register all settings, sections, and fields.
	 */
	public function register_settings(): void {

		// --- LLM Section ---
		add_settings_section(
			'grayfox_llm',
			__( 'LLM Provider', 'kbfox' ),
			array( $this, 'render_llm_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'grayfox_llm_provider', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_llm_provider' ),
			'default'           => 'openai',
		) );

		add_settings_field(
			'grayfox_llm_provider',
			__( 'Provider', 'kbfox' ),
			array( $this, 'render_llm_provider_field' ),
			self::PAGE_SLUG,
			'grayfox_llm'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_llm_api_key', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_llm_api_key' ),
			'default'           => '',
		) );

		add_settings_field(
			'grayfox_llm_api_key',
			__( 'API Key', 'kbfox' ),
			array( $this, 'render_llm_api_key_field' ),
			self::PAGE_SLUG,
			'grayfox_llm'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_llm_model', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_llm_model' ),
			'default'           => '',
		) );

		add_settings_field(
			'grayfox_llm_model',
			__( 'Model', 'kbfox' ),
			array( $this, 'render_llm_model_field' ),
			self::PAGE_SLUG,
			'grayfox_llm'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_llm_max_tokens', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_max_tokens' ),
			'default'           => 1024,
		) );

		add_settings_field(
			'grayfox_llm_max_tokens',
			__( 'Max Response Tokens', 'kbfox' ),
			array( $this, 'render_llm_max_tokens_field' ),
			self::PAGE_SLUG,
			'grayfox_llm'
		);

		// --- Appearance Section ---
		add_settings_section(
			'grayfox_appearance',
			__( 'Widget Appearance', 'kbfox' ),
			array( $this, 'render_appearance_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'grayfox_widget_name', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => 'Chat with us',
		) );

		add_settings_field(
			'grayfox_widget_name',
			__( 'Widget Name', 'kbfox' ),
			array( $this, 'render_widget_name_field' ),
			self::PAGE_SLUG,
			'grayfox_appearance'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_widget_color', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_hex_color',
			'default'           => '#6366f1',
		) );

		add_settings_field(
			'grayfox_widget_color',
			__( 'Primary Color', 'kbfox' ),
			array( $this, 'render_widget_color_field' ),
			self::PAGE_SLUG,
			'grayfox_appearance'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_widget_position', array(
			'type'              => 'string',
			'sanitize_callback' => array( $this, 'sanitize_position' ),
			'default'           => 'bottom-right',
		) );

		add_settings_field(
			'grayfox_widget_position',
			__( 'Widget Position', 'kbfox' ),
			array( $this, 'render_widget_position_field' ),
			self::PAGE_SLUG,
			'grayfox_appearance'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_widget_welcome_message', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_textarea_field',
			'default'           => 'Hello! How can I help you today?',
		) );

		add_settings_field(
			'grayfox_widget_welcome_message',
			__( 'Welcome Message', 'kbfox' ),
			array( $this, 'render_welcome_message_field' ),
			self::PAGE_SLUG,
			'grayfox_appearance'
		);

		// --- Behavior Section ---
		add_settings_section(
			'grayfox_behavior',
			__( 'Behavior', 'kbfox' ),
			array( $this, 'render_behavior_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'grayfox_enable_widget', array(
			'type'              => 'boolean',
			'sanitize_callback' => 'rest_sanitize_boolean',
			'default'           => true,
		) );

		add_settings_field(
			'grayfox_enable_widget',
			__( 'Enable Widget', 'kbfox' ),
			array( $this, 'render_enable_widget_field' ),
			self::PAGE_SLUG,
			'grayfox_behavior'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_business_phone', array(
			'type'              => 'string',
			'sanitize_callback' => 'sanitize_text_field',
			'default'           => '',
		) );

		add_settings_field(
			'grayfox_business_phone',
			__( 'Business Phone', 'kbfox' ),
			array( $this, 'render_business_phone_field' ),
			self::PAGE_SLUG,
			'grayfox_behavior'
		);

		// --- Conversation Limits Section ---
		add_settings_section(
			'grayfox_conversation_limits',
			__( 'Conversation Limits & Abuse Protection', 'kbfox' ),
			array( $this, 'render_conversation_limits_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'grayfox_session_message_limit', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_session_message_limit' ),
			'default'           => 21,
		) );

		add_settings_field(
			'grayfox_session_message_limit',
			__( 'Max messages per session', 'kbfox' ),
			array( $this, 'render_session_message_limit_field' ),
			self::PAGE_SLUG,
			'grayfox_conversation_limits'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_ip_sessions_per_hour', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_ip_sessions_per_hour' ),
			'default'           => 5,
		) );

		add_settings_field(
			'grayfox_ip_sessions_per_hour',
			__( 'Max sessions per IP per hour', 'kbfox' ),
			array( $this, 'render_ip_sessions_per_hour_field' ),
			self::PAGE_SLUG,
			'grayfox_conversation_limits'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_ip_sessions_per_day', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_ip_sessions_per_day' ),
			'default'           => 10,
		) );

		add_settings_field(
			'grayfox_ip_sessions_per_day',
			__( 'Max sessions per IP per day', 'kbfox' ),
			array( $this, 'render_ip_sessions_per_day_field' ),
			self::PAGE_SLUG,
			'grayfox_conversation_limits'
		);

		add_settings_section(
			'grayfox_public_kb_api',
			__( 'Public KB API', 'kbfox' ),
			array( $this, 'render_public_kb_api_section' ),
			self::PAGE_SLUG
		);

		register_setting( self::OPTION_GROUP, 'grayfox_public_kb_api_enabled', array(
			'type'              => 'boolean',
			'sanitize_callback' => array( $this, 'sanitize_checkbox' ),
			'default'           => false,
		) );

		add_settings_field(
			'grayfox_public_kb_api_enabled',
			__( 'Enable public KB API', 'kbfox' ),
			array( $this, 'render_public_kb_api_enabled_field' ),
			self::PAGE_SLUG,
			'grayfox_public_kb_api'
		);

		register_setting( self::OPTION_GROUP, 'grayfox_public_kb_api_rate_limit', array(
			'type'              => 'integer',
			'sanitize_callback' => array( $this, 'sanitize_public_kb_api_rate_limit' ),
			'default'           => 60,
		) );

		add_settings_field(
			'grayfox_public_kb_api_rate_limit',
			__( 'Rate limit (requests per IP per hour)', 'kbfox' ),
			array( $this, 'render_public_kb_api_rate_limit_field' ),
			self::PAGE_SLUG,
			'grayfox_public_kb_api'
		);

		do_action( 'grayfox_register_settings', self::PAGE_SLUG, self::OPTION_GROUP );
	}

	/* -----------------------------------------------------------
	 * Section descriptions
	 * --------------------------------------------------------- */

	/** Render LLM section description. */
	public function render_llm_section(): void {
		echo '<p>' . esc_html__( 'Configure your AI provider. The API key is stored encrypted and never sent to the browser.', 'kbfox' ) . '</p>';
	}

	/** Render appearance section description. */
	public function render_appearance_section(): void {
		echo '<p>' . esc_html__( 'Customize how the chat widget looks on your site.', 'kbfox' ) . '</p>';
	}

	/** Render behavior section description. */
	public function render_behavior_section(): void {
		echo '<p>' . esc_html__( 'Control when and where the widget appears.', 'kbfox' ) . '</p>';
	}

	/* -----------------------------------------------------------
	 * Field renderers
	 * --------------------------------------------------------- */

	/** Render LLM provider dropdown. */
	public function render_llm_provider_field(): void {
		$value = get_option( 'grayfox_llm_provider', 'openai' );
		$providers = array(
			'openai'    => 'OpenAI',
			'anthropic' => 'Anthropic',
			'gemini'    => 'Google Gemini',
			'groq'      => 'Groq',
		);
		?>
		<select id="grayfox_llm_provider" name="grayfox_llm_provider">
			<?php foreach ( $providers as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $value, $key ); ?>>
					<?php echo esc_html( $label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<?php
	}

	/** Render LLM API key field (masked). */
	public function render_llm_api_key_field(): void {
		$encrypted = get_option( 'grayfox_llm_api_key', '' );
		$masked    = ! empty( $encrypted ) ? str_repeat( '*', 32 ) : '';
		?>
		<div style="display:flex;align-items:center;gap:8px;">
			<input type="password"
				   id="grayfox_llm_api_key"
				   name="grayfox_llm_api_key"
				   value="<?php echo esc_attr( $masked ); ?>"
				   class="regular-text"
				   autocomplete="new-password"
				   placeholder="<?php esc_attr_e( 'Enter new key to update', 'kbfox' ); ?>" />
			<button type="button" id="grayfox-toggle-key" class="button button-secondary">
				<?php esc_html_e( 'Show', 'kbfox' ); ?>
			</button>
		</div>
		<p class="description">
			<?php esc_html_e( 'Stored encrypted. Leave blank to keep existing key.', 'kbfox' ); ?>
		</p>
		<script>
		(function() {
			var toggleBtn = document.getElementById('grayfox-toggle-key');
			var keyField  = document.getElementById('grayfox_llm_api_key');
			if (!toggleBtn || !keyField) return;
			toggleBtn.addEventListener('click', function() {
				if (keyField.type === 'password') {
					keyField.type = 'text';
					toggleBtn.textContent = '<?php echo esc_js( __( 'Hide', 'kbfox' ) ); ?>';
				} else {
					keyField.type = 'password';
					toggleBtn.textContent = '<?php echo esc_js( __( 'Show', 'kbfox' ) ); ?>';
				}
			});
		})();
		</script>
		<?php
	}

	/**
	 * Return per-model pricing (USD per 1M tokens) and the date prices were last verified.
	 *
	 * IMPORTANT: Update this table when providers change their pricing.
	 * Keys: input_per_1m, output_per_1m, verified_date
	 *
	 * @param string $model Model ID.
	 * @return array|null Null if model is unknown.
	 */
	public static function get_model_pricing( string $model ): ?array {
		$prices = array(
			// OpenAI
			'gpt-5.4-mini'             => array( 'input_per_1m' => 0.75,  'output_per_1m' => 4.50,  'verified_date' => 'Mar 2026' ),
			'gpt-5.4'                  => array( 'input_per_1m' => 5.00,  'output_per_1m' => 20.00, 'verified_date' => 'Mar 2026' ),
			'gpt-4.1'                  => array( 'input_per_1m' => 2.00,  'output_per_1m' => 8.00,  'verified_date' => 'Mar 2026' ),
			'gpt-4.1-mini'             => array( 'input_per_1m' => 0.40,  'output_per_1m' => 1.60,  'verified_date' => 'Mar 2026' ),
			'gpt-4o'                   => array( 'input_per_1m' => 2.50,  'output_per_1m' => 10.00, 'verified_date' => 'Mar 2026' ),
			'gpt-4o-mini'              => array( 'input_per_1m' => 0.15,  'output_per_1m' => 0.60,  'verified_date' => 'Mar 2026' ),
			// Anthropic
			'claude-opus-4-6'              => array( 'input_per_1m' => 15.00, 'output_per_1m' => 75.00, 'verified_date' => 'Mar 2026' ),
			'claude-sonnet-4-6'            => array( 'input_per_1m' => 3.00,  'output_per_1m' => 15.00, 'verified_date' => 'Mar 2026' ),
			'claude-haiku-4-5-20251001'    => array( 'input_per_1m' => 0.80,  'output_per_1m' => 4.00,  'verified_date' => 'Mar 2026' ),
			'claude-3-5-sonnet-20241022'   => array( 'input_per_1m' => 3.00,  'output_per_1m' => 15.00, 'verified_date' => 'Mar 2026' ),
			'claude-3-5-haiku-20241022'    => array( 'input_per_1m' => 0.80,  'output_per_1m' => 4.00,  'verified_date' => 'Mar 2026' ),
			// Gemini
			'gemini-2.0-flash'         => array( 'input_per_1m' => 0.075, 'output_per_1m' => 0.30,  'verified_date' => 'Mar 2026' ),
			'gemini-2.0-flash-lite'    => array( 'input_per_1m' => 0.075, 'output_per_1m' => 0.30,  'verified_date' => 'Mar 2026' ),
			'gemini-1.5-pro'           => array( 'input_per_1m' => 1.25,  'output_per_1m' => 5.00,  'verified_date' => 'Mar 2026' ),
			'gemini-1.5-flash'         => array( 'input_per_1m' => 0.075, 'output_per_1m' => 0.30,  'verified_date' => 'Mar 2026' ),
			// Groq
			'llama-3.3-70b-versatile'  => array( 'input_per_1m' => 0.59,  'output_per_1m' => 0.79,  'verified_date' => 'Mar 2026' ),
		);
		return $prices[ $model ] ?? null;
	}

	/**
	 * Return the billing dashboard URL for a provider.
	 *
	 * @param string $provider Provider slug.
	 * @return string URL.
	 */
	public static function get_provider_dashboard_url( string $provider ): string {
		$urls = array(
			'openai'    => 'https://platform.openai.com/usage',
			'anthropic' => 'https://console.anthropic.com/settings/billing',
			'gemini'    => 'https://console.cloud.google.com/billing',
			'groq'      => 'https://console.groq.com/settings/billing',
		);
		return $urls[ $provider ] ?? '#';
	}

	/**
	 * Return the cheapest/lowest-tier classifier model for a given provider.
	 *
	 * Always the last entry in each provider's model list — update there only.
	 *
	 * @param string $provider Provider slug.
	 * @return string Model ID.
	 */
	public static function get_classifier_model( string $provider ): string {
		$models = self::get_models_by_provider();
		$list   = $models[ $provider ] ?? array();
		if ( empty( $list ) ) {
			return '';
		}
		$ids = array_keys( $list );
		return end( $ids );
	}

	/**
	 * Return supported models keyed by provider.
	 *
	 * Update this list when providers release new models.
	 * The LAST entry in each provider list is used as the classifier model.
	 *
	 * @return array<string, array<string, string>>
	 */
	public static function get_models_by_provider(): array {
		return array(
			'openai'    => array(
				'gpt-5.4-mini' => 'GPT-5.4 Mini',
				'gpt-5.4'      => 'GPT-5.4',
				'gpt-4.1'      => 'GPT-4.1',
				'gpt-4.1-mini' => 'GPT-4.1 Mini',
				'gpt-4o'       => 'GPT-4o',
				'gpt-4o-mini'  => 'GPT-4o Mini',
			),
			'anthropic' => array(
				'claude-opus-4-6'              => 'Claude Opus 4.6',
				'claude-sonnet-4-6'            => 'Claude Sonnet 4.6',
				'claude-haiku-4-5-20251001'    => 'Claude Haiku 4.5',
				'claude-3-5-sonnet-20241022'   => 'Claude 3.5 Sonnet',
				'claude-3-5-haiku-20241022'    => 'Claude 3.5 Haiku',
			),
			'gemini'    => array(
				'gemini-2.0-flash'        => 'Gemini 2.0 Flash',
				'gemini-2.0-flash-lite'   => 'Gemini 2.0 Flash Lite',
				'gemini-1.5-pro'          => 'Gemini 1.5 Pro',
				'gemini-1.5-flash'        => 'Gemini 1.5 Flash',
			),
			'groq'      => array(
				'llama-3.3-70b-versatile'  => 'Llama 3.3 70B Versatile',
			),
		);
	}

	/** Render LLM model dropdown with Test Connection button. */
	public function render_llm_model_field(): void {
		$saved_model  = get_option( 'grayfox_llm_model', '' );
		$saved_prov   = get_option( 'grayfox_llm_provider', 'openai' );
		$all_models   = self::get_models_by_provider();
		$models_json  = wp_json_encode( $all_models );
		?>
		<select id="grayfox_llm_model" name="grayfox_llm_model">
			<?php
			$current_models = $all_models[ $saved_prov ] ?? array();
			foreach ( $current_models as $model_id => $model_label ) :
				?>
				<option value="<?php echo esc_attr( $model_id ); ?>" <?php selected( $saved_model, $model_id ); ?>>
					<?php echo esc_html( $model_label ); ?>
				</option>
			<?php endforeach; ?>
		</select>
		<button type="button" id="grayfox-test-llm" class="button button-secondary" style="margin-left:8px;">
			<?php esc_html_e( 'Test Connection', 'kbfox' ); ?>
		</button>
		<span id="grayfox-test-llm-result" style="margin-left:8px;"></span>
		<script>
		(function() {
			var allModels  = <?php echo $models_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;
			var provSelect = document.getElementById('grayfox_llm_provider');
			var mdlSelect  = document.getElementById('grayfox_llm_model');
			var savedModel = <?php echo wp_json_encode( $saved_model ); ?>;

			function populateModels(provider) {
				var models = allModels[provider] || {};
				mdlSelect.innerHTML = '';
				Object.keys(models).forEach(function(id) {
					var opt = document.createElement('option');
					opt.value = id;
					opt.textContent = models[id];
					if (id === savedModel) opt.selected = true;
					mdlSelect.appendChild(opt);
				});
			}

			if (provSelect) {
				provSelect.addEventListener('change', function() {
					savedModel = ''; // clear saved preference on provider switch
					populateModels(this.value);
				});
			}

			var btn = document.getElementById('grayfox-test-llm');
			if (!btn) return;
			btn.addEventListener('click', function() {
				var result = document.getElementById('grayfox-test-llm-result');
				result.textContent = '<?php echo esc_js( __( 'Testing...', 'kbfox' ) ); ?>';
				result.style.color = '#666';
				btn.disabled = true;

				var data = new FormData();
				data.append('action', 'grayfox_test_llm');
				data.append('_wpnonce', '<?php echo esc_js( wp_create_nonce( 'grayfox_test_llm' ) ); ?>');

				fetch(ajaxurl, { method: 'POST', body: data })
					.then(function(r) { return r.json(); })
					.then(function(resp) {
						btn.disabled = false;
						if (resp.success) {
							result.textContent = resp.data.message || '<?php echo esc_js( __( 'Connected!', 'kbfox' ) ); ?>';
							result.style.color = 'green';
						} else {
							result.textContent = resp.data || '<?php echo esc_js( __( 'Connection failed.', 'kbfox' ) ); ?>';
							result.style.color = 'red';
						}
					})
					.catch(function() {
						btn.disabled = false;
						result.textContent = '<?php echo esc_js( __( 'Network error.', 'kbfox' ) ); ?>';
						result.style.color = 'red';
					});
			});
		})();
		</script>
		<?php
	}

	/** Render max response tokens field. */
	public function render_llm_max_tokens_field(): void {
		$value = (int) get_option( 'grayfox_llm_max_tokens', 1024 );
		?>
		<input type="number"
			   id="grayfox_llm_max_tokens"
			   name="grayfox_llm_max_tokens"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="small-text"
			   min="64"
			   max="32000"
			   step="1" />
		<p class="description">
			<?php esc_html_e( 'Maximum tokens the model may generate per response. Required by Anthropic; applied to all providers.', 'kbfox' ); ?>
		</p>
		<?php
	}

	/** Render widget name field. */
	public function render_widget_name_field(): void {
		$value = get_option( 'grayfox_widget_name', 'Chat with us' );
		?>
		<input type="text"
			   id="grayfox_widget_name"
			   name="grayfox_widget_name"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text" />
		<?php
	}

	/** Render widget color field. */
	public function render_widget_color_field(): void {
		$value = get_option( 'grayfox_widget_color', '#6366f1' );
		?>
		<input type="color"
			   id="grayfox_widget_color"
			   name="grayfox_widget_color"
			   value="<?php echo esc_attr( $value ); ?>" />
		<code id="grayfox-color-display"><?php echo esc_html( $value ); ?></code>
		<script>
		(function() {
			var input = document.getElementById('grayfox_widget_color');
			var display = document.getElementById('grayfox-color-display');
			if (input && display) {
				input.addEventListener('input', function() { display.textContent = input.value; });
			}
		})();
		</script>
		<?php
	}

	/** Render widget position dropdown. */
	public function render_widget_position_field(): void {
		$value = get_option( 'grayfox_widget_position', 'bottom-right' );
		?>
		<select id="grayfox_widget_position" name="grayfox_widget_position">
			<option value="bottom-right" <?php selected( $value, 'bottom-right' ); ?>>
				<?php esc_html_e( 'Bottom Right', 'kbfox' ); ?>
			</option>
			<option value="bottom-left" <?php selected( $value, 'bottom-left' ); ?>>
				<?php esc_html_e( 'Bottom Left', 'kbfox' ); ?>
			</option>
		</select>
		<?php
	}

	/** Render welcome message textarea. */
	public function render_welcome_message_field(): void {
		$value = get_option( 'grayfox_widget_welcome_message', 'Hello! Who am I speaking with today?' );
		?>
		<textarea id="grayfox_widget_welcome_message"
				  name="grayfox_widget_welcome_message"
				  rows="3"
				  class="large-text"><?php echo esc_textarea( $value ); ?></textarea>
		<?php
	}

	/** Render enable widget checkbox. */
	public function render_enable_widget_field(): void {
		$value = get_option( 'grayfox_enable_widget', true );
		?>
		<label>
			<input type="checkbox"
				   id="grayfox_enable_widget"
				   name="grayfox_enable_widget"
				   value="1"
				   <?php checked( $value ); ?> />
			<?php esc_html_e( 'Show floating chat widget on all pages', 'kbfox' ); ?>
		</label>
		<?php
	}

	/** Render business phone field. */
	public function render_business_phone_field(): void {
		$value = get_option( 'grayfox_business_phone', '' );
		?>
		<input type="text"
			   id="grayfox_business_phone"
			   name="grayfox_business_phone"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="regular-text"
			   placeholder="+1 (555) 000-0000" />
		<p class="description">
			<?php esc_html_e( 'Displayed in the chat when a visitor reaches the session message limit.', 'kbfox' ); ?>
		</p>
		<?php
	}

	/** Render public KB API section description. */
	public function render_public_kb_api_section(): void {
		echo '<p>' . esc_html__( 'Expose your knowledge base as a public REST API so AI agents (ChatGPT, Claude, etc.) can discover and query it. Only enable this if your KB contains customer-facing information — it will be publicly accessible without authentication.', 'kbfox' ) . '</p>';
	}

	/** Render conversation limits section description. */
	public function render_conversation_limits_section(): void {
		echo '<p>' . esc_html__( 'Control how many messages a visitor can send and how many chat sessions are allowed per IP address. These settings help prevent abuse and excessive API usage.', 'kbfox' ) . '</p>';
	}

	/** Render session message limit field. */
	public function render_session_message_limit_field(): void {
		$value   = (int) get_option( 'grayfox_session_message_limit', 21 );
		$default = 21;
		?>
		<input type="number"
			   id="grayfox_session_message_limit"
			   name="grayfox_session_message_limit"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="small-text"
			   min="5"
			   max="50"
			   step="1" />
		<a href="#" class="grayfox-restore-default" data-target="grayfox_session_message_limit" data-default="<?php echo esc_attr( $default ); ?>" style="margin-left:8px;">
			<?php esc_html_e( 'Restore default', 'kbfox' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Maximum number of messages (user + assistant) allowed per chat session. Range: 5–50. Default: 21.', 'kbfox' ); ?>
		</p>
		<?php
	}

	/** Render IP sessions per hour field. */
	public function render_ip_sessions_per_hour_field(): void {
		$value   = (int) get_option( 'grayfox_ip_sessions_per_hour', 5 );
		$default = 5;
		?>
		<input type="number"
			   id="grayfox_ip_sessions_per_hour"
			   name="grayfox_ip_sessions_per_hour"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="small-text"
			   min="1"
			   max="10"
			   step="1" />
		<a href="#" class="grayfox-restore-default" data-target="grayfox_ip_sessions_per_hour" data-default="<?php echo esc_attr( $default ); ?>" style="margin-left:8px;">
			<?php esc_html_e( 'Restore default', 'kbfox' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Maximum new chat sessions an IP address may start within one hour. Range: 1–10. Default: 5.', 'kbfox' ); ?>
		</p>
		<?php
	}

	/** Render IP sessions per day field. */
	public function render_ip_sessions_per_day_field(): void {
		$value   = (int) get_option( 'grayfox_ip_sessions_per_day', 10 );
		$default = 10;
		?>
		<input type="number"
			   id="grayfox_ip_sessions_per_day"
			   name="grayfox_ip_sessions_per_day"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="small-text"
			   min="1"
			   max="25"
			   step="1" />
		<a href="#" class="grayfox-restore-default" data-target="grayfox_ip_sessions_per_day" data-default="<?php echo esc_attr( $default ); ?>" style="margin-left:8px;">
			<?php esc_html_e( 'Restore default', 'kbfox' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Maximum new chat sessions an IP address may start within 24 hours. Range: 1–25. Default: 10.', 'kbfox' ); ?>
		</p>
		<script>
		(function() {
			document.querySelectorAll('.grayfox-restore-default').forEach(function(link) {
				link.addEventListener('click', function(e) {
					e.preventDefault();
					var input = document.getElementById(this.dataset.target);
					if (input) input.value = this.dataset['default'];
				});
			});
		})();
		</script>
		<?php
	}

	/** Render public KB API enabled toggle. */
	public function render_public_kb_api_enabled_field(): void {
		$enabled      = (bool) get_option( 'grayfox_public_kb_api_enabled', false );
		$endpoint_url = rest_url( 'grayfox/v1/kb' );
		$llms_url     = home_url( '/llms.txt' );
		?>
		<label>
			<input type="checkbox"
				   id="grayfox_public_kb_api_enabled"
				   name="grayfox_public_kb_api_enabled"
				   value="1"
				   <?php checked( $enabled ); ?> />
			<?php esc_html_e( 'Allow public unauthenticated access to the knowledge base', 'kbfox' ); ?>
		</label>
		<?php if ( $enabled ) : ?>
		<table class="form-table" style="margin-top:12px;">
			<tr>
				<th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e( 'Endpoint', 'kbfox' ); ?></th>
				<td>
					<code><?php echo esc_url( $endpoint_url ); ?></code>
					<button type="button" class="button button-small grayfox-copy-url" data-url="<?php echo esc_attr( $endpoint_url ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Copy', 'kbfox' ); ?></button>
				</td>
			</tr>
			<tr>
				<th style="padding:4px 10px 4px 0;font-weight:normal;"><?php esc_html_e( 'llms.txt', 'kbfox' ); ?></th>
				<td>
					<code><?php echo esc_url( $llms_url ); ?></code>
					<button type="button" class="button button-small grayfox-copy-url" data-url="<?php echo esc_attr( $llms_url ); ?>" style="margin-left:8px;"><?php esc_html_e( 'Copy', 'kbfox' ); ?></button>
				</td>
			</tr>
		</table>
		<script>
		(function() {
			document.querySelectorAll('.grayfox-copy-url').forEach(function(btn) {
				btn.addEventListener('click', function() {
					navigator.clipboard.writeText(this.dataset.url).then(function() {
						btn.textContent = '<?php echo esc_js( __( 'Copied!', 'kbfox' ) ); ?>';
						setTimeout(function() {
							btn.textContent = '<?php echo esc_js( __( 'Copy', 'kbfox' ) ); ?>';
						}, 2000);
					});
				});
			});
		})();
		</script>
		<?php endif; ?>
		<?php
	}

	/** Render public KB API rate limit field. */
	public function render_public_kb_api_rate_limit_field(): void {
		$value   = (int) get_option( 'grayfox_public_kb_api_rate_limit', 60 );
		$default = 60;
		?>
		<input type="number"
			   id="grayfox_public_kb_api_rate_limit"
			   name="grayfox_public_kb_api_rate_limit"
			   value="<?php echo esc_attr( $value ); ?>"
			   class="small-text"
			   min="10"
			   max="600"
			   step="10" />
		<a href="#" class="grayfox-restore-default" data-target="grayfox_public_kb_api_rate_limit" data-default="<?php echo esc_attr( $default ); ?>" style="margin-left:8px;">
			<?php esc_html_e( 'Restore default', 'kbfox' ); ?>
		</a>
		<p class="description">
			<?php esc_html_e( 'Maximum API requests allowed per IP address per hour. Range: 10–600. Default: 60.', 'kbfox' ); ?>
		</p>
		<?php
	}

	/* -----------------------------------------------------------
	 * Sanitizers
	 * --------------------------------------------------------- */

	/**
	 * Sanitize a checkbox value to boolean.
	 *
	 * @param mixed $input Raw input.
	 * @return bool
	 */
	public function sanitize_checkbox( $input ): bool {
		return (bool) $input;
	}

	/**
	 * Sanitize public KB API rate limit: integer clamped 10–600, default 60.
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_public_kb_api_rate_limit( $input ): int {
		$val = (int) $input;
		if ( $val < 10 || $val > 600 ) {
			return 60;
		}
		return $val;
	}

	/**
	 * Sanitize LLM provider to allowed values.
	 *
	 * @param string $input Raw input.
	 * @return string
	 */
	public function sanitize_llm_provider( string $input ): string {
		$allowed = array( 'openai', 'anthropic', 'gemini', 'groq' );
		return in_array( $input, $allowed, true ) ? $input : 'openai';
	}

	/**
	 * Sanitize LLM model to supported values for the saved provider.
	 *
	 * @param string $input Raw input.
	 * @return string
	 */
	public function sanitize_llm_model( string $input ): string {
		$input    = sanitize_text_field( $input );
		$provider = sanitize_text_field( $_POST['grayfox_llm_provider'] ?? get_option( 'grayfox_llm_provider', 'openai' ) ); // phpcs:ignore WordPress.Security.NonceVerification.Missing
		$models   = self::get_models_by_provider();
		$allowed  = array_keys( $models[ $provider ] ?? array() );
		if ( in_array( $input, $allowed, true ) ) {
			return $input;
		}
		// Fall back to the first model for the provider rather than empty.
		return ! empty( $allowed ) ? $allowed[0] : '';
	}

	/**
	 * Sanitize and encrypt the LLM API key on save.
	 *
	 * @param string $input Raw input.
	 * @return string Encrypted value, or existing value if blank/masked.
	 */
	public function sanitize_llm_api_key( string $input ): string {
		$input = sanitize_text_field( $input );
		if ( empty( $input ) ) {
			return get_option( 'grayfox_llm_api_key', '' );
		}
		// If value looks like masked placeholder, keep existing.
		if ( preg_match( '/^\*+$/', $input ) ) {
			return get_option( 'grayfox_llm_api_key', '' );
		}
		// WordPress calls sanitize_option twice when adding a new option for
		// the first time (once in update_option, again in add_option). The
		// 'gf1:' prefix lets us detect an already-encrypted value and skip
		// re-encryption on the second call.
		if ( strpos( $input, 'gf1:' ) === 0 ) {
			return $input;
		}
		return grayfox_encrypt( $input );
	}

	/**
	 * Sanitize max tokens: integer clamped between 64 and 32000.
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_max_tokens( $input ): int {
		return max( 64, min( 32000, (int) $input ) );
	}

	/**
	 * Sanitize widget position.
	 *
	 * @param string $input Raw input.
	 * @return string
	 */
	public function sanitize_position( string $input ): string {
		$allowed = array( 'bottom-right', 'bottom-left' );
		return in_array( $input, $allowed, true ) ? $input : 'bottom-right';
	}

	/**
	 * Sanitize session message limit: integer clamped 5–50, default 21.
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_session_message_limit( $input ): int {
		$val = (int) $input;
		if ( $val < 5 || $val > 50 ) {
			return 21;
		}
		return $val;
	}

	/**
	 * Sanitize IP sessions per hour: integer clamped 1–10, default 5.
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_ip_sessions_per_hour( $input ): int {
		$val = (int) $input;
		if ( $val < 1 || $val > 10 ) {
			return 5;
		}
		return $val;
	}

	/**
	 * Sanitize IP sessions per day: integer clamped 1–25, default 10.
	 *
	 * @param mixed $input Raw input.
	 * @return int
	 */
	public function sanitize_ip_sessions_per_day( $input ): int {
		$val = (int) $input;
		if ( $val < 1 || $val > 25 ) {
			return 10;
		}
		return $val;
	}

	/* -----------------------------------------------------------
	 * AJAX handlers
	 * --------------------------------------------------------- */

	/**
	 * AJAX handler: test LLM provider connectivity with the saved credentials.
	 *
	 * Makes a minimal (max_tokens=1) non-streaming request to confirm the key
	 * and model are accepted by the provider.
	 */
	public function ajax_test_llm(): void {
		check_ajax_referer( 'grayfox_test_llm' );

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( __( 'Unauthorized.', 'kbfox' ) );
		}

		$provider      = get_option( 'grayfox_llm_provider', 'openai' );
		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		$model         = get_option( 'grayfox_llm_model', '' );

		if ( empty( $encrypted_key ) ) {
			wp_send_json_error( __( 'No API key saved. Save your settings first.', 'kbfox' ) );
		}

		$api_key = grayfox_decrypt( $encrypted_key );
		if ( empty( $api_key ) ) {
			wp_send_json_error( __( 'Failed to decrypt API key.', 'kbfox' ) );
		}

		if ( empty( $model ) ) {
			wp_send_json_error( __( 'No model configured. Enter a model name and save first.', 'kbfox' ) );
		}

		$error = $this->probe_llm_provider( $provider, $api_key, $model );

		if ( null === $error ) {
			wp_send_json_success( array(
				'message' => sprintf(
					/* translators: 1: provider name, 2: model name */
					__( 'Connected! Provider: %1$s, Model: %2$s', 'kbfox' ),
					esc_html( $provider ),
					esc_html( $model )
				),
			) );
		} else {
			wp_send_json_error( $error );
		}
	}

	/**
	 * Send a minimal probe request to the LLM provider.
	 *
	 * @param string $provider Provider slug.
	 * @param string $api_key  Plaintext API key.
	 * @param string $model    Model name.
	 * @return string|null Null on success, error message string on failure.
	 */
	private function probe_llm_provider( string $provider, string $api_key, string $model ): ?string {
		switch ( $provider ) {
			case 'openai':
				$payload  = wp_json_encode( array(
					'model'                 => $model,
					'messages'              => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
					'max_completion_tokens' => 5,
				) );
				$response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', array(
					'headers'     => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'        => $payload,
					'timeout'     => 15,
					'data_format' => 'body',
				) );
				break;

			case 'groq':
				$payload  = wp_json_encode( array(
					'model'                 => $model,
					'messages'              => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
					'max_completion_tokens' => 5,
				) );
				$response = wp_remote_post( 'https://api.groq.com/openai/v1/chat/completions', array(
					'headers'     => array(
						'Authorization' => 'Bearer ' . $api_key,
						'Content-Type'  => 'application/json',
					),
					'body'        => $payload,
					'timeout'     => 15,
					'data_format' => 'body',
				) );
				break;

			case 'anthropic':
				$max_tokens = max( 64, min( 32000, (int) get_option( 'grayfox_llm_max_tokens', 1024 ) ) );
				$payload    = wp_json_encode( array(
					'model'      => $model,
					'max_tokens' => $max_tokens,
					'messages'   => array( array( 'role' => 'user', 'content' => 'Hi' ) ),
				) );
				$response = wp_remote_post( 'https://api.anthropic.com/v1/messages', array(
					'headers'     => array(
						'x-api-key'         => $api_key,
						'anthropic-version' => '2023-06-01',
						'Content-Type'      => 'application/json',
					),
					'body'        => $payload,
					'timeout'     => 15,
					'data_format' => 'body',
				) );
				break;

			case 'gemini':
				$url      = 'https://generativelanguage.googleapis.com/v1beta/models/'
					. rawurlencode( $model ) . ':generateContent?key=' . rawurlencode( $api_key );
				$payload  = wp_json_encode( array(
					'contents'          => array( array( 'parts' => array( array( 'text' => 'Hi' ) ) ) ),
					'generationConfig'  => array( 'maxOutputTokens' => 1 ),
				) );
				$response = wp_remote_post( $url, array(
					'headers'     => array( 'Content-Type' => 'application/json' ),
					'body'        => $payload,
					'timeout'     => 15,
					'data_format' => 'body',
				) );
				break;

			default:
				return __( 'Unknown provider.', 'kbfox' );
		}

		if ( is_wp_error( $response ) ) {
			return $response->get_error_message();
		}

		$code = wp_remote_retrieve_response_code( $response );
		if ( $code >= 200 && $code < 300 ) {
			return null;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		$msg  = $body['error']['message'] ?? $body['message'] ?? wp_remote_retrieve_response_message( $response );
		return sprintf(
			/* translators: 1: HTTP code, 2: error message */
			__( 'Error %1$d: %2$s', 'kbfox' ),
			$code,
			$msg
		);
	}
}
