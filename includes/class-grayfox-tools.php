<?php
/**
 * Tool registry and built-in tool implementations.
 *
 * Tools follow OpenAI function-calling schema internally. Provider-specific
 * translation happens in GrayFox_LLM before each API call.
 *
 * To add a new tool:
 *   1. Create a class extending GrayFox_Tool.
 *   2. Call GrayFox_Tools::register( new MyTool() ) inside GrayFox_Tools::init().
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Abstract base class for a GrayFox tool.
 */
abstract class GrayFox_Tool {

	/**
	 * Unique snake_case tool name. Must match the 'name' in get_definition().
	 */
	abstract public function get_name(): string;

	/**
	 * Tool definition in OpenAI function-calling format:
	 * { type: 'function', function: { name, description, parameters: { JSON Schema } } }
	 */
	abstract public function get_definition(): array;

	/**
	 * Execute the tool with arguments provided by the LLM.
	 *
	 * @param array $args Decoded arguments from the LLM tool call.
	 * @return string Result string (JSON or plain text) returned to the LLM.
	 */
	abstract public function execute( array $args ): string;
}

/**
 * Tool: search_knowledge_base
 *
 * Searches the business knowledge base for information relevant to the user's
 * question. The LLM formulates a contextual query using conversation history,
 * which is more accurate than passing the raw user message directly.
 *
 */
class GrayFox_Tool_Search_KB extends GrayFox_Tool {

	public function get_name(): string {
		return 'search_knowledge_base';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'search_knowledge_base',
				'description' => 'Search the business knowledge base to find information relevant to the user\'s question. '
					. 'Use this whenever the user asks about services, pricing, fees, rates, hours, schedule, '
					. 'policies, procedures, contact info, FAQs, or any other business-specific details. '
					. 'Formulate the query using the full conversation context — resolve pronouns and references '
					. '(e.g. "it", "that service", "the price you mentioned") before searching.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'query' => array(
							'type'        => 'string',
							'description' => 'A specific, context-aware search query derived from the user\'s intent and conversation history.',
						),
					),
					'required'             => array( 'query' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$query = isset( $args['query'] ) ? sanitize_text_field( (string) $args['query'] ) : '';

		if ( empty( $query ) ) {
			return wp_json_encode( array( 'error' => 'No query provided.' ) );
		}

		$knowledge = GrayFox_RAG::get_consolidated_knowledge( $query );

		if ( empty( $knowledge ) ) {
			return wp_json_encode( array( 'result' => 'No relevant information found in the knowledge base for this query.' ) );
		}

		return $knowledge;
	}
}

/**
 * Tool: capture_customer_email
 *
 * Saves a customer's email address (and optionally name and area of interest)
 * to the current conversation record. Fires a WordPress action so other plugin
 * components (e.g. CRM integrations) can hook in.
 *
 * Call this when a customer expresses genuine interest in a service and
 * voluntarily provides or agrees to share their email.
 */
class GrayFox_Tool_Capture_Email extends GrayFox_Tool {

	public function get_name(): string {
		return 'capture_customer_email';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'capture_customer_email',
				'description' => 'Save a customer\'s email address when they voluntarily provide it or agree to be contacted. '
					. 'Only call this after the customer has explicitly shared their email. '
					. 'Do not ask for an email unless the customer has expressed clear interest in a specific service.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'email'    => array(
							'type'        => 'string',
							'description' => 'The customer\'s email address.',
						),
						'name'     => array(
							'type'        => 'string',
							'description' => 'The customer\'s name, if they have shared it.',
						),
						'interest' => array(
							'type'        => 'string',
							'description' => 'Brief description of the service or topic the customer expressed interest in.',
						),
					),
					'required'             => array( 'email' ),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$email    = isset( $args['email'] )    ? sanitize_email( $args['email'] )         : '';
		$name     = isset( $args['name'] )     ? sanitize_text_field( $args['name'] )     : '';
		$interest = isset( $args['interest'] ) ? sanitize_text_field( $args['interest'] ) : '';

		if ( empty( $email ) || ! is_email( $email ) ) {
			return wp_json_encode( array( 'error' => 'Invalid email address provided.' ) );
		}

		$conversation_id = GrayFox_Tools::get_conversation_id();

		if ( $conversation_id > 0 ) {
			global $wpdb;
			$conv_table = GrayFox_DB::get_table( 'conversations' );
			$update_data = array( 'visitor_email' => $email );
			$update_format = array( '%s' );
			if ( ! empty( $name ) ) {
				$update_data['visitor_name'] = $name;
				$update_format[]             = '%s';
			}
			$wpdb->update( $conv_table, $update_data, array( 'id' => $conversation_id ), $update_format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
		}

		/**
		 * Fires when a customer email is captured via the chat assistant.
		 *
		 * @param string $email           Validated email address.
		 * @param string $name            Customer name (may be empty).
		 * @param string $interest        Service or topic of interest (may be empty).
		 * @param int    $conversation_id Conversation DB row ID (0 if unavailable).
		 */
		do_action( 'grayfox_lead_captured', $email, $name, $interest, $conversation_id );

		return wp_json_encode( array(
			'success'        => true,
			'email_captured' => $email,
			'message'        => 'Email saved successfully. Do not ask for the email again — reference it as ' . $email . ' in any follow-up.',
		) );
	}
}

/**
 * Tool registry.
 *
 * Manages all registered tools and filters them by license tier.
 * Initialized lazily on first access.
 */
class GrayFox_Tools {

	/** @var GrayFox_Tool[] Registered tools keyed by name. */
	private static array $registry = array();

	/** @var bool Whether built-in tools have been registered. */
	private static bool $initialized = false;

	/** @var int Current conversation ID, set before each agentic loop. */
	private static int $conversation_id = 0;

	/**
	 * Set the current conversation context before running the agentic loop.
	 * Called from GrayFox_Chat::handle_chat() so tools can reference the conversation.
	 *
	 * @param int $conversation_id Conversation DB row ID.
	 */
	public static function set_context( int $conversation_id ): void {
		self::$conversation_id = $conversation_id;
	}

	/**
	 * Get the current conversation ID.
	 *
	 * @return int
	 */
	public static function get_conversation_id(): int {
		return self::$conversation_id;
	}

	/**
	 * Register a tool instance.
	 *
	 * @param GrayFox_Tool $tool Tool to register.
	 */
	public static function register( GrayFox_Tool $tool ): void {
		self::$registry[ $tool->get_name() ] = $tool;
	}

	/**
	 * Register built-in tools. Called lazily on first access.
	 * Add future tools here with their required tier.
	 */
	private static function init(): void {
		if ( self::$initialized ) {
			return;
		}
		self::$initialized = true;

		self::register( new GrayFox_Tool_Search_KB() );
		self::register( new GrayFox_Tool_Capture_Email() );

		/**
		 * Fires after built-in tools are registered, allowing external plugins
		 * to add their own tools to the LLM's function-calling toolkit.
		 *
		 * Use GrayFox_Tools::register() inside this hook to add a tool.
		 * Each tool must extend the GrayFox_Tool abstract class.
		 *
		 * @since 1.0.0
		 * @param class-string<GrayFox_Tools> $tools_class The GrayFox_Tools class name.
		 */
		do_action( 'grayfox_register_tools', GrayFox_Tools::class );
	}

	/**
	 * Get all registered tools.
	 *
	 * @return GrayFox_Tool[]
	 */
	public static function get_all(): array {
		self::init();
		return array_values( self::$registry );
	}

	/**
	 * Get OpenAI-format tool definitions for all registered tools.
	 * Pass the result directly to request_with_tools().
	 *
	 * @return array[]
	 */
	public static function get_definitions(): array {
		return array_map( static fn( $t ) => $t->get_definition(), self::get_all() );
	}

	/**
	 * Backwards-compatible alias used by GrayFox_Chat.
	 *
	 * @param string $tier Ignored — all tools are now available.
	 * @return array[]
	 */
	public static function get_definitions_for_tier( string $tier ): array {
		return self::get_definitions();
	}

	/**
	 * Execute a tool by name with the given arguments.
	 *
	 * @param string $name Tool name as returned by the LLM.
	 * @param array  $args Decoded arguments from the LLM tool call.
	 * @return string Tool result returned to the LLM.
	 */
	public static function execute( string $name, array $args ): string {
		self::init();

		if ( ! isset( self::$registry[ $name ] ) ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox Tools: unknown tool called: ' . $name ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return wp_json_encode( array( 'error' => 'Unknown tool: ' . sanitize_text_field( $name ) ) );
		}

		try {
			return self::$registry[ $name ]->execute( $args );
		} catch ( \Throwable $e ) {
			if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
				error_log( 'GrayFox Tools: error executing "' . $name . '": ' . $e->getMessage() ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log
			}
			return wp_json_encode( array( 'error' => 'Tool execution failed.' ) );
		}
	}
}
