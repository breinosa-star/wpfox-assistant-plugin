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
if ( ! class_exists( 'GrayFox_Tool' ) ) {
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
} // end class_exists GrayFox_Tool

/**
 * Tool: search_knowledge_base
 *
 * Searches the business knowledge base for information relevant to the user's
 * question. The LLM formulates a contextual query using conversation history,
 * which is more accurate than passing the raw user message directly.
 *
 */
if ( ! class_exists( 'GrayFox_Tool_Search_KB' ) ) {
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
} // end class_exists GrayFox_Tool_Search_KB

/**
 * Tool: capture_customer_email
 *
 * Saves a customer's contact details (email and/or phone, plus optional name
 * and area of interest) to the current conversation record. At least one of
 * email or phone is required. Fires a WordPress action for CRM integrations.
 */
if ( ! class_exists( 'GrayFox_Tool_Capture_Email' ) ) {
class GrayFox_Tool_Capture_Email extends GrayFox_Tool {

	public function get_name(): string {
		return 'capture_customer_email';
	}

	public function get_definition(): array {
		return array(
			'type'     => 'function',
			'function' => array(
				'name'        => 'capture_customer_email',
				'description' => 'Save a customer\'s contact details when they voluntarily provide them and agree to be contacted. '
					. 'At least one of email or phone is required; both are optional individually. '
					. 'Only call this after the customer has explicitly shared their contact information and expressed clear interest in a service.',
				'parameters'  => array(
					'type'       => 'object',
					'properties' => array(
						'email'    => array(
							'type'        => 'string',
							'description' => 'The customer\'s email address (optional if phone is provided).',
						),
						'phone'    => array(
							'type'        => 'string',
							'description' => 'The customer\'s phone number (optional if email is provided).',
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
					'required'             => array(),
					'additionalProperties' => false,
				),
			),
		);
	}

	public function execute( array $args ): string {
		$email    = isset( $args['email'] )    ? sanitize_email( $args['email'] )               : '';
		$phone    = isset( $args['phone'] )    ? sanitize_text_field( $args['phone'] )           : '';
		$name     = isset( $args['name'] )     ? sanitize_text_field( $args['name'] )            : '';
		$interest = isset( $args['interest'] ) ? sanitize_text_field( $args['interest'] )        : '';

		$has_email = ! empty( $email ) && is_email( $email );
		$has_phone = ! empty( $phone );

		if ( ! $has_email && ! $has_phone ) {
			return wp_json_encode( array( 'error' => 'At least one of email or phone is required.' ) );
		}

		$conversation_id = GrayFox_Tools::get_conversation_id();

		if ( $conversation_id > 0 ) {
			global $wpdb;
			$conv_table    = GrayFox_DB::get_table( 'conversations' );
			$update_data   = array();
			$update_format = array();
			if ( $has_email ) {
				$update_data['visitor_email'] = $email;
				$update_format[]              = '%s';
			}
			if ( $has_phone ) {
				$update_data['visitor_phone'] = $phone;
				$update_format[]              = '%s';
			}
			if ( ! empty( $name ) ) {
				$update_data['visitor_name'] = $name;
				$update_format[]             = '%s';
			}
			$wpdb->update( $conv_table, $update_data, array( 'id' => $conversation_id ), $update_format, array( '%d' ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		}

		/**
		 * Fires when a customer lead is captured via the chat assistant.
		 *
		 * @param string $email           Validated email address (may be empty).
		 * @param string $name            Customer name (may be empty).
		 * @param string $interest        Service or topic of interest (may be empty).
		 * @param int    $conversation_id Conversation DB row ID (0 if unavailable).
		 * @param string $phone           Phone number (may be empty).
		 */
		do_action( 'grayfox_lead_captured', $email, $name, $interest, $conversation_id, $phone );

		$captured = array();
		if ( $has_email ) { $captured[] = 'email: ' . $email; }
		if ( $has_phone ) { $captured[] = 'phone: ' . $phone; }

		return wp_json_encode( array(
			'success' => true,
			'message' => 'Contact details saved (' . implode( ', ', $captured ) . '). Do not ask for contact information again.',
		) );
	}
}
} // end class_exists GrayFox_Tool_Capture_Email

/**
 * Tool registry.
 *
 * Manages all registered tools and filters them by license tier.
 * Initialized lazily on first access.
 */
if ( ! class_exists( 'GrayFox_Tools' ) ) {
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
			return wp_json_encode( array( 'error' => 'Unknown tool: ' . sanitize_text_field( $name ) ) );
		}

		try {
			return self::$registry[ $name ]->execute( $args );
		} catch ( \Throwable $e ) {
			return wp_json_encode( array( 'error' => 'Tool execution failed.' ) );
		}
	}
}
} // end class_exists GrayFox_Tools
