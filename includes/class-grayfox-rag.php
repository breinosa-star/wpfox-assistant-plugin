<?php
/**
 * RAG-Lite document processor.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class GrayFox_RAG
 *
 * Processes uploaded documents into a structured knowledge base via Action Scheduler.
 */
class GrayFox_RAG {

	/**
	 * Action Scheduler hook for document processing.
	 *
	 * @var string
	 */
	const AS_HOOK = 'grayfox_process_document';

	/**
	 * Stop words excluded from query tokenization.
	 *
	 * @var string[]
	 */
	private static array $stop_words = array(
		// Articles, determiners, conjunctions
		'the', 'and', 'but', 'for', 'nor', 'yet', 'that', 'this', 'these',
		'those', 'some', 'any', 'all', 'both', 'each', 'few', 'more', 'most',
		'other', 'such', 'same', 'also',
		// Pronouns
		'you', 'your', 'they', 'their', 'them', 'there', 'we', 'our', 'its',
		'who', 'whom', 'whose', 'which',
		// Auxiliary / modal verbs
		'have', 'has', 'had', 'does', 'did', 'will', 'would', 'could', 'should',
		'may', 'might', 'can', 'been', 'being', 'were', 'was', 'are', 'not',
		// Question words
		'what', 'when', 'where', 'how', 'why',
		// Prepositions
		'with', 'from', 'about', 'into', 'than', 'then', 'over', 'under',
		'between', 'through', 'during', 'before', 'after', 'above', 'below',
		// Filler / conversational
		'please', 'tell', 'know', 'want', 'like', 'give', 'need', 'just',
		'very', 'much', 'many', 'even', 'still', 'also', 'well', 'here',
	);

	/**
	 * Singleton instance.
	 *
	 * @var GrayFox_RAG|null
	 */
	private static ?GrayFox_RAG $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return GrayFox_RAG
	 */
	public static function get_instance(): GrayFox_RAG {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Document limits per tier.
	 *
	 * @var array
	 */
	private static array $tier_limits = array(
		'starter'    => 20,
		'growth'     => 100,
		'beast_mode' => PHP_INT_MAX,
		'pro'        => PHP_INT_MAX,
		'free'       => 15,
		''           => 5, // No license: allow a small trial.
	);

	/**
	 * Register hooks with the loader.
	 *
	 * @param GrayFox_Loader $loader Loader instance.
	 */
	public function register( GrayFox_Loader $loader ): void {
		$loader->add_action( self::AS_HOOK, $this, 'process_document', 10, 1 );
		$loader->add_action( 'init', $this, 'register_as_callback', 5 );
	}

	/**
	 * Register the Action Scheduler callback on the init hook.
	 */
	public function register_as_callback(): void {
		add_action( self::AS_HOOK, array( $this, 'process_document' ) );
	}

	/**
	 * Schedule an Action Scheduler job to process an uploaded document.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 */
	public static function schedule_processing( int $attachment_id ): void {
		global $wpdb;
		$kb_table    = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_table  = esc_sql( $kb_table );
		$source_name = get_the_title( $attachment_id );
		if ( empty( $source_name ) ) {
			$file        = get_attached_file( $attachment_id );
			$source_name = $file ? basename( $file ) : (string) $attachment_id;
		}

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$safe_table}` WHERE source_type = 'upload' AND source_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(string) $attachment_id
		) );

		if ( ! $existing ) {
			$wpdb->insert(
				$kb_table,
				array(
					'source_type'       => 'upload',
					'source_id'         => (string) $attachment_id,
					'source_name'       => $source_name,
					'content_json'      => null,
					'token_estimate'    => 0,
					'last_processed_at' => null,
					'status'            => 'pending',
					'topic_index'       => null,
					'created_at'        => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', null, '%d', null, '%s', null, '%s' )
			);
		}

		if ( ! function_exists( 'as_enqueue_async_action' ) ) {
			$rag = new self();
			$rag->process_document( $attachment_id );
			return;
		}

		as_enqueue_async_action( self::AS_HOOK, array( $attachment_id ), 'grayfox' );
	}

	/**
	 * Process an uploaded document attachment into the knowledge base.
	 *
	 * @param int $attachment_id WordPress attachment post ID.
	 */
	public function process_document( int $attachment_id ): void {
		$file_path = get_attached_file( $attachment_id );
		if ( ! $file_path || ! file_exists( $file_path ) ) {
			return;
		}

		$source_name = get_the_title( $attachment_id );
		if ( empty( $source_name ) ) {
			$source_name = basename( $file_path );
		}

		if ( ! $this->check_tier_limit() ) {
			return;
		}

		$extension = strtolower( pathinfo( $file_path, PATHINFO_EXTENSION ) );
		$raw_text  = $this->extract_text( $file_path, $extension );

		global $wpdb;
		$kb_table      = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_kb_table = esc_sql( $kb_table );

		$existing = $wpdb->get_var( $wpdb->prepare(
			"SELECT id FROM `{$safe_kb_table}` WHERE source_type = 'upload' AND source_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			(string) $attachment_id
		) );
		$existing_id = $existing ? (int) $existing : 0;

		// Handle PDF-specific sentinel values.
		if ( '' === $raw_text && 'pdf' === $extension ) {
			$this->upsert_kb_row( $existing_id, $attachment_id, 'upload', $source_name, null, 0, 'active', wp_json_encode( array( '_warning' => 'pdf_library' ) ) );
			return;
		}

		if ( '__PDF_NO_TEXT__' === $raw_text ) {
			$this->upsert_kb_row( $existing_id, $attachment_id, 'upload', $source_name, null, 0, 'active', wp_json_encode( array( '_warning' => 'pdf_no_text' ) ) );
			return;
		}

		if ( empty( $raw_text ) ) {
			return;
		}

		$token_estimate = (int) ceil( mb_strlen( $raw_text ) / 4 );

		// Hybrid chunking: single pass if fits, chunked if too large.
		if ( mb_strlen( $raw_text ) <= 60000 ) {
			$content_json = $this->summarize_with_llm( $raw_text, $source_name );
		} else {
			error_log( 'GrayFox RAG: document "' . $source_name . '" exceeds 60k chars (' . mb_strlen( $raw_text ) . '), using chunked summarization.' );
			$content_json = $this->summarize_chunked( $raw_text, $source_name );
		}

		if ( empty( $content_json ) ) {
			// Summarization failed — store null, surface retry button in admin.
			$this->upsert_kb_row( $existing_id, $attachment_id, 'upload', $source_name, null, $token_estimate, 'active', wp_json_encode( array( '_error' => 'summarization_failed' ) ) );
			return;
		}

		// Build topic index from structured JSON.
		$decoded     = json_decode( $content_json, true );
		$topic_array = is_array( $decoded ) ? self::build_topic_index( $decoded ) : array();
		$topic_json  = wp_json_encode( $topic_array );

		// Save the row first (status='active') so we have a stable doc ID for conflict storage.
		$this->upsert_kb_row( $existing_id, $attachment_id, 'upload', $source_name, $content_json, $token_estimate, 'active', $topic_json );
		$new_doc_id = $existing_id > 0 ? $existing_id : (int) $wpdb->insert_id;

		// Detect conflicts now that we have the doc ID. Updates status to pending_review if needed.
		$status = $this->detect_and_flag_conflicts( $topic_array, $new_doc_id, $source_name, $new_doc_id );
		if ( 'pending_review' === $status ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				GrayFox_DB::get_table( 'knowledge_base' ),
				array( 'status' => 'pending_review' ),
				array( 'id'     => $new_doc_id ),
				array( '%s' ),
				array( '%d' )
			);
		}

		// Trigger onboarding hint after first successfully active document.
		if ( 'active' === $status ) {
			$active_count = (int) $wpdb->get_var(
				"SELECT COUNT(*) FROM `{$safe_kb_table}` WHERE status = 'active'" // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
			);
			if ( 1 === $active_count ) {
				set_transient( 'grayfox_kb_first_doc_ready', 1, 0 );
			}
		}
	}

	/**
	 * Get the consolidated knowledge base as a single JSON string.
	 *
	 * Accepts an optional query string; when provided, only sections relevant
	 * to the query are returned (retrieval layer). When empty, all active
	 * documents are returned (up to the 80k character cap).
	 *
	 * @param string $query Optional user message for relevance-based retrieval.
	 * @return string Merged JSON string for LLM context, or empty string.
	 */
	public static function get_consolidated_knowledge( string $query = '' ): string {
		global $wpdb;
		$kb_table = esc_sql( $wpdb->prefix . 'grayfox_knowledge_base' );

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$rows = $wpdb->get_results(
			"SELECT id, source_name, content_json, topic_index, token_estimate, last_processed_at FROM `{$kb_table}` WHERE content_json IS NOT NULL AND content_json != '' AND status = 'active' ORDER BY last_processed_at DESC",
			ARRAY_A
		);

		if ( empty( $rows ) ) {
			return '';
		}

		if ( ! empty( $query ) ) {
			$knowledge = self::retrieve_relevant_sections( $query, $rows );
		} else {
			$knowledge = array();
			foreach ( $rows as $row ) {
				$decoded     = json_decode( $row['content_json'], true );
				$knowledge[] = array(
					'document' => $row['source_name'],
					'content'  => $decoded ?? $row['content_json'],
				);
			}
		}

		// 80k character total cap — drop oldest/smallest documents first.
		$total_chars = mb_strlen( wp_json_encode( $knowledge ) );
		if ( $total_chars > 80000 ) {
			// Sort by token_estimate ascending (smallest first to drop) using a stable sort.
			usort( $knowledge, static function ( $a, $b ) {
				return strtotime( $a['last_processed_at'] ?? '0' ) <=> strtotime( $b['last_processed_at'] ?? '0' );
			} );
			// Trim from the end (oldest) until under cap.
			while ( mb_strlen( wp_json_encode( $knowledge ) ) > 80000 && count( $knowledge ) > 1 ) {
				array_pop( $knowledge );
			}
		}

		return wp_json_encode( $knowledge );
	}

	/**
	 * Retrieve only the knowledge base sections relevant to a query.
	 *
	 * Uses keyword matching against each document's topic_index. Falls back
	 * to short summaries when no match is found. Handles cross-document
	 * deduplication for high-overlap pairs.
	 *
	/**
	 * LLM-as-retriever fallback. Called when lexical scoring returns zero matches.
	 *
	 * Collects all topic keys across the KB, asks the LLM which are relevant to the
	 * user query, then returns documents that contain any of the selected topics.
	 * Returns empty array if the LLM selects no topics or the call fails.
	 *
	 * @param string  $query   User message or search string.
	 * @param array[] $kb_rows Raw rows from the knowledge_base table (ARRAY_A).
	 * @return array Array of ['document' => string, 'content' => mixed] items.
	 */
	private static function retrieve_with_llm( string $query, array $kb_rows ): array {
		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key       = grayfox_decrypt( $encrypted_key );
		$provider      = get_option( 'grayfox_llm_provider', 'openai' );
		$model         = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			return array();
		}

		// Collect all unique non-alias topic keys across every document.
		$all_topics = array();
		$doc_topic_map = array(); // topic_key => [row, ...]
		foreach ( $kb_rows as $row ) {
			if ( empty( $row['content_json'] ) ) {
				continue;
			}
			$decoded = json_decode( $row['content_json'], true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			foreach ( array_keys( $decoded ) as $topic_key ) {
				if ( '_aliases' === $topic_key ) {
					continue;
				}
				$all_topics[] = $topic_key;
				$doc_topic_map[ $topic_key ][] = $row;
			}
		}

		$all_topics = array_values( array_unique( $all_topics ) );
		if ( empty( $all_topics ) ) {
			return array();
		}

		$topic_list = implode( ', ', $all_topics );
		$messages   = array(
			array(
				'role'    => 'system',
				'content' => GRAYFOX_PROMPT_RAG_RETRIEVE,
			),
			array(
				'role'    => 'user',
				'content' => "Available topics: {$topic_list}\n\nUser question: {$query}",
			),
		);

		$llm    = new GrayFox_LLM();
		$result = $llm->request_json( $provider, $api_key, $model, $messages, 0.0 );

		if ( empty( $result ) ) {
			error_log( 'GrayFox RAG: LLM-as-retriever returned empty for query: ' . mb_substr( $query, 0, 100 ) );
			return array();
		}

		$selected_topics = json_decode( $result, true );
		if ( ! is_array( $selected_topics ) || empty( $selected_topics ) ) {
			return array();
		}

		// Collect documents that contain any of the selected topic keys, deduplicated by row ID.
		$matched_rows = array();
		$seen_ids     = array();
		foreach ( $selected_topics as $topic_key ) {
			if ( ! isset( $doc_topic_map[ $topic_key ] ) ) {
				continue;
			}
			foreach ( $doc_topic_map[ $topic_key ] as $row ) {
				$row_id = $row['id'] ?? null;
				if ( null !== $row_id && in_array( $row_id, $seen_ids, true ) ) {
					continue;
				}
				$seen_ids[]     = $row_id;
				$matched_rows[] = $row;
			}
		}

		$result_items = array();
		foreach ( $matched_rows as $row ) {
			$decoded        = json_decode( $row['content_json'], true );
			$result_items[] = array(
				'document' => $row['source_name'],
				'content'  => $decoded ?? $row['content_json'],
			);
		}

		return $result_items;
	}

	/**
	 * @param string  $query   User message or search string.
	 * @param array[] $kb_rows Raw rows from the knowledge_base table (ARRAY_A).
	 * @return array Array of ['document' => string, 'content' => mixed] items.
	 */
	public static function retrieve_relevant_sections( string $query, array $kb_rows ): array {

		// Tokenize query.
		$raw_terms   = preg_split( '/\s+/', mb_strtolower( trim( $query ) ) );
		$query_terms = array_values( array_filter( $raw_terms, static function ( $w ) {
			return '' !== $w && ! in_array( $w, self::$stop_words, true );
		} ) );

		// Score each row.
		$scored = array();
		foreach ( $kb_rows as $row ) {
			$topics = array();
			if ( ! empty( $row['topic_index'] ) ) {
				$decoded = json_decode( $row['topic_index'], true );
				if ( is_array( $decoded ) ) {
					$topics = array_map( 'mb_strtolower', $decoded );
				}
			}

			$score = 0;
			foreach ( $query_terms as $term ) {
				foreach ( $topics as $topic ) {
					if ( false !== mb_strpos( $topic, $term ) ) {
						$score++;
						break; // Count term once per document.
					}
				}
			}

			$scored[] = array(
				'row'    => $row,
				'topics' => $topics,
				'score'  => $score,
			);
		}

		// Sort by score descending.
		usort( $scored, static function ( $a, $b ) {
			return $b['score'] <=> $a['score'];
		} );

		// Fallback: no lexical matches — use LLM-as-retriever to select relevant topics semantically.
		if ( empty( $scored ) || $scored[0]['score'] === 0 ) {
			return self::retrieve_with_llm( $query, $kb_rows );
		}

		// Keep only rows with score > 0.
		$relevant = array_filter( $scored, static fn( $s ) => $s['score'] > 0 );
		$relevant = array_values( $relevant );

		// Cross-document deduplication for high topic overlap.
		$result    = array();
		$used_ids  = array();

		foreach ( $relevant as $i => $item ) {
			$row_id = $item['row']['id'] ?? $i;
			if ( in_array( $row_id, $used_ids, true ) ) {
				continue;
			}

			$has_conflict = false;
			foreach ( $relevant as $j => $other ) {
				if ( $i === $j ) {
					continue;
				}
				$other_id = $other['row']['id'] ?? $j;
				if ( in_array( $other_id, $used_ids, true ) ) {
					continue;
				}

				$overlap_count = count( array_intersect( $item['topics'], $other['topics'] ) );
				$min_size      = max( 1, min( count( $item['topics'] ), count( $other['topics'] ) ) );
				$overlap_ratio = $overlap_count / $min_size;

				if ( $overlap_ratio > 0.8 ) {
					// High overlap — keep newer (earlier in list, sorted desc by last_processed_at).
					$used_ids[] = $other_id;

					// Check if content actually conflicts.
					$content_a = json_decode( $item['row']['content_json'], true );
					$content_b = json_decode( $other['row']['content_json'], true );

					if ( $content_a !== $content_b ) {
						// Content differs — serve both with conflict note.
						$overlapping = implode( ', ', array_slice( array_intersect( $item['topics'], $other['topics'] ), 0, 3 ) );
						$result[]    = array(
							'document'      => $item['row']['source_name'] . ' (vs. ' . $other['row']['source_name'] . ')',
							'content'       => $content_a ?? $item['row']['content_json'],
							'_conflict_note' => "NOTE: conflicting information found across documents for topic '{$overlapping}' — present both versions to the user and ask them to clarify",
							'conflict_content' => $content_b ?? $other['row']['content_json'],
						);
						$has_conflict = true;
					}
					// If identical content, just skip the duplicate.
				}
			}

			if ( $has_conflict ) {
				$used_ids[] = $row_id;
			} else {
				$decoded  = json_decode( $item['row']['content_json'], true );
				$result[] = array(
					'document' => $item['row']['source_name'],
					'content'  => $decoded ?? $item['row']['content_json'],
				);
			}

			$used_ids[] = $row_id;
		}

		return $result;
	}

	/**
	 * Build a flat topic index array from a structured content_json array.
	 *
	 * @param array $content_json Decoded JSON content from the LLM summary.
	 * @return string[] Lowercase topic strings (max 200 items).
	 */
	public static function build_topic_index( array $content_json ): array {
		$topics = array();

		foreach ( $content_json as $key => $value ) {
			// _aliases: extract alias words for each topic and add directly to index.
			if ( '_aliases' === $key && is_array( $value ) ) {
				foreach ( $value as $alias_string ) {
					if ( is_string( $alias_string ) ) {
						$alias_words = preg_split( '/[|\s\-]+/u', $alias_string, -1, PREG_SPLIT_NO_EMPTY );
						foreach ( $alias_words as $w ) {
							$topics[] = mb_strtolower( $w );
						}
					}
				}
				continue;
			}

			$topics[] = mb_strtolower( (string) $key );

			if ( is_array( $value ) ) {
				foreach ( $value as $item ) {
					if ( is_string( $item ) ) {
						$words = preg_split( '/[\s\p{P}]+/u', $item, -1, PREG_SPLIT_NO_EMPTY );
						foreach ( $words as $w ) {
							if ( mb_strlen( $w ) >= 3 ) {
								$topics[] = mb_strtolower( $w );
							}
						}
					} elseif ( is_array( $item ) ) {
						foreach ( $item as $sub_key => $sub_val ) {
							$topics[] = mb_strtolower( (string) $sub_key );
							if ( is_string( $sub_val ) ) {
								$words = preg_split( '/[\s\p{P}]+/u', $sub_val, -1, PREG_SPLIT_NO_EMPTY );
								foreach ( $words as $w ) {
									if ( mb_strlen( $w ) >= 3 ) {
										$topics[] = mb_strtolower( $w );
									}
								}
							}
						}
					}
				}
			} elseif ( is_string( $value ) ) {
				$words = preg_split( '/[\s\p{P}]+/u', $value, -1, PREG_SPLIT_NO_EMPTY );
				foreach ( $words as $w ) {
					if ( mb_strlen( $w ) >= 3 ) {
						$topics[] = mb_strtolower( $w );
					}
				}
			}
		}

		$topics = array_values( array_unique( $topics ) );
		return array_slice( $topics, 0, 200 );
	}

	/**
	 * Check whether more documents can be added based on the current license tier.
	 *
	 * @return bool True if the doc limit has not been reached.
	 */
	public function check_tier_limit(): bool {
		global $wpdb;
		$kb_table      = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_kb_table = esc_sql( $kb_table );

		$count = (int) $wpdb->get_var(
			"SELECT COUNT(*) FROM `{$safe_kb_table}`" // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
		);

		$tier  = (string) get_option( 'grayfox_license_tier', '' );
		$limit = self::$tier_limits[ $tier ] ?? self::$tier_limits[''];

		return $count < $limit;
	}

	/**
	 * Summarize raw text into a structured JSON knowledge-base entry and upsert
	 * it into the knowledge_base table. Used by Google Drive sync paths.
	 *
	 * @param string      $text        Raw document text to summarise.
	 * @param string      $source_name Human-readable file/document name.
	 * @param string|null $source_id   Unique external ID (Drive file ID) or null for uploads.
	 */
	public function summarize_to_knowledge_base( string $text, string $source_name, string $source_id = null ): void {
		if ( empty( trim( $text ) ) ) {
			return;
		}

		$token_estimate = (int) ceil( mb_strlen( $text ) / 4 );

		// Hybrid chunking: single pass if fits, chunked if too large.
		if ( mb_strlen( $text ) <= 60000 ) {
			$content_json = $this->summarize_with_llm( $text, $source_name );
		} else {
			error_log( 'GrayFox RAG: document "' . $source_name . '" exceeds 60k chars (' . mb_strlen( $text ) . '), using chunked summarization.' );
			$content_json = $this->summarize_chunked( $text, $source_name );
		}

		global $wpdb;
		$kb_table      = GrayFox_DB::get_table( 'knowledge_base' );
		$safe_kb_table = esc_sql( $kb_table );

		// Determine existing ID.
		$existing_id = 0;
		if ( null !== $source_id ) {
			$existing = $wpdb->get_var( $wpdb->prepare(
				"SELECT id FROM `{$safe_kb_table}` WHERE source_id = %s LIMIT 1", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$source_id
			) );
			$existing_id = $existing ? (int) $existing : 0;
		}

		if ( empty( $content_json ) ) {
			$this->upsert_kb_row_drive( $existing_id, $source_id, $source_name, null, $token_estimate, 'active', wp_json_encode( array( '_error' => 'summarization_failed' ) ) );
			return;
		}

		$decoded     = json_decode( $content_json, true );
		$topic_array = is_array( $decoded ) ? self::build_topic_index( $decoded ) : array();
		$topic_json  = wp_json_encode( $topic_array );

		// Save first so we have a stable doc ID for conflict storage.
		$this->upsert_kb_row_drive( $existing_id, $source_id, $source_name, $content_json, $token_estimate, 'active', $topic_json );
		$new_doc_id = $existing_id > 0 ? $existing_id : (int) $wpdb->insert_id;

		$status = $this->detect_and_flag_conflicts( $topic_array, $new_doc_id, $source_name, $new_doc_id );
		if ( 'pending_review' === $status ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$kb_table,
				array( 'status' => 'pending_review' ),
				array( 'id'     => $new_doc_id ),
				array( '%s' ),
				array( '%d' )
			);
		}
	}

	/* ------------------------------------------------------------------
	 * Private helpers
	 * ------------------------------------------------------------------ */

	/**
	 * Detect topic conflicts with existing active documents and flag the new
	 * document for review if conflicts are found.
	 *
	 * @param string[] $new_topics   Topic index for the new document.
	 * @param int      $exclude_id   Row ID to exclude from comparison (same as new_doc_id after upsert).
	 * @param string   $source_name  Name of the new document.
	 * @param int      $new_doc_id   DB row ID of the newly saved document.
	 * @return string 'pending_review' if conflicts found, 'active' otherwise.
	 */
	private function detect_and_flag_conflicts( array $new_topics, int $exclude_id, string $source_name, int $new_doc_id = 0 ): string {
		if ( empty( $new_topics ) ) {
			return 'active';
		}

		$conflicts = $this->detect_conflicts( $new_topics, $exclude_id );

		if ( empty( $conflicts ) ) {
			return 'active';
		}

		// Save one conflict entry per conflicting pair so the resolution UI can handle them individually.
		$pending = (array) get_option( 'grayfox_pending_conflicts', array() );
		foreach ( $conflicts as $conflict ) {
			$pending[] = array(
				'new_doc_id'         => $new_doc_id,
				'new_source_name'    => $source_name,
				'old_doc_id'         => (int) $conflict['id'],
				'old_source_name'    => $conflict['source_name'],
				'overlapping_topics' => $conflict['overlapping_topics'],
				'detected_at'        => current_time( 'mysql', true ),
			);
		}
		update_option( 'grayfox_pending_conflicts', $pending );
		set_transient( 'grayfox_conflict_notice', $source_name, 0 );

		return 'pending_review';
	}

	/**
	 * Find existing active documents whose topic index overlaps with new_topics.
	 *
	 * @param string[] $new_topics  Topics from the document being processed.
	 * @param int      $exclude_id  Row ID to exclude (0 for new inserts).
	 * @return array[] Conflicting rows: [['id', 'source_name', 'overlapping_topics']].
	 */
	private function detect_conflicts( array $new_topics, int $exclude_id ): array {
		global $wpdb;
		$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

		if ( $exclude_id > 0 ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results( $wpdb->prepare(
				"SELECT id, source_name, topic_index FROM `{$kb_table}` WHERE status = 'active' AND id != %d",
				$exclude_id
			), ARRAY_A );
		} else {
			// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared, WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$rows = $wpdb->get_results(
				"SELECT id, source_name, topic_index FROM `{$kb_table}` WHERE status = 'active'",
				ARRAY_A
			);
		}

		$conflicts = array();
		foreach ( $rows as $row ) {
			if ( empty( $row['topic_index'] ) ) {
				continue;
			}
			$existing_topics = json_decode( $row['topic_index'], true );
			if ( ! is_array( $existing_topics ) ) {
				continue;
			}

			$overlapping  = array_values( array_intersect( $new_topics, $existing_topics ) );
			$overlap_count = count( $overlapping );
			$min_size      = max( 1, min( count( $new_topics ), count( $existing_topics ) ) );
			$ratio         = $overlap_count / $min_size;

			if ( $ratio > 0.3 ) {
				$conflicts[] = array(
					'id'                => (int) $row['id'],
					'source_name'       => $row['source_name'],
					'overlapping_topics' => array_slice( $overlapping, 0, 10 ),
				);
			}
		}

		return $conflicts;
	}

	/**
	 * Upsert a knowledge base row for upload-sourced documents.
	 */
	private function upsert_kb_row( int $existing_id, int $attachment_id, string $source_type, string $source_name, ?string $content_json, int $token_estimate, string $status, ?string $topic_index ): void {
		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		if ( $existing_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$kb_table,
				array(
					'source_name'       => $source_name,
					'content_json'      => $content_json,
					'token_estimate'    => $token_estimate,
					'last_processed_at' => current_time( 'mysql', true ),
					'status'            => $status,
					'topic_index'       => $topic_index,
				),
				array( 'id' => $existing_id ),
				array( '%s', '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$kb_table,
				array(
					'source_type'       => $source_type,
					'source_id'         => (string) $attachment_id,
					'source_name'       => $source_name,
					'content_json'      => $content_json,
					'token_estimate'    => $token_estimate,
					'last_processed_at' => current_time( 'mysql', true ),
					'status'            => $status,
					'topic_index'       => $topic_index,
					'created_at'        => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Upsert a knowledge base row for Drive-sourced documents.
	 */
	private function upsert_kb_row_drive( int $existing_id, ?string $source_id, string $source_name, ?string $content_json, int $token_estimate, string $status, ?string $topic_index ): void {
		global $wpdb;
		$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

		if ( $existing_id > 0 ) {
			$wpdb->update( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$kb_table,
				array(
					'source_name'       => $source_name,
					'content_json'      => $content_json,
					'token_estimate'    => $token_estimate,
					'last_processed_at' => current_time( 'mysql', true ),
					'status'            => $status,
					'topic_index'       => $topic_index,
				),
				array( 'id' => $existing_id ),
				array( '%s', '%s', '%d', '%s', '%s', '%s' ),
				array( '%d' )
			);
		} else {
			$wpdb->insert( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
				$kb_table,
				array(
					'source_type'       => 'google_drive',
					'source_id'         => $source_id,
					'source_name'       => $source_name,
					'content_json'      => $content_json,
					'token_estimate'    => $token_estimate,
					'last_processed_at' => current_time( 'mysql', true ),
					'status'            => $status,
					'topic_index'       => $topic_index,
					'created_at'        => current_time( 'mysql', true ),
				),
				array( '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
			);
		}
	}

	/**
	 * Extract text from a file based on its extension.
	 *
	 * @param string $file_path  Absolute file path.
	 * @param string $extension  Lowercase file extension.
	 * @return string Extracted text, or sentinel values for PDF edge cases.
	 */
	private function extract_text( string $file_path, string $extension ): string {
		switch ( $extension ) {
			case 'txt':
			case 'csv':
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $file_path );
				return ( false === $content ) ? '' : $content;

			case 'md':
				// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents
				$content = file_get_contents( $file_path );
				if ( false === $content ) {
					return '';
				}
				// Strip common markdown syntax so the LLM sees plain prose.
				$content = preg_replace( '/^#{1,6}\s+/m', '', $content );       // headings
				$content = preg_replace( '/!\[.*?\]\(.*?\)/', '', $content );    // images
				$content = preg_replace( '/\[([^\]]+)\]\([^)]+\)/', '$1', $content ); // links → label
				$content = preg_replace( '/(\*\*|__)(.*?)\1/', '$2', $content ); // bold
				$content = preg_replace( '/(\*|_)(.*?)\1/', '$2', $content );    // italic
				$content = preg_replace( '/`{1,3}[^`]*`{1,3}/', '', $content ); // inline/fenced code
				$content = preg_replace( '/^```.*?^```/ms', '', $content );      // fenced code blocks
				$content = preg_replace( '/^\s*[-*+]\s+/m', '', $content );      // unordered list markers
				$content = preg_replace( '/^\s*\d+\.\s+/m', '', $content );      // ordered list markers
				$content = preg_replace( '/^\s*>\s?/m', '', $content );          // blockquotes
				$content = preg_replace( '/^\s*[-*_]{3,}\s*$/m', '', $content ); // horizontal rules
				return trim( $content );

			case 'pdf':
				return $this->extract_pdf_text( $file_path );

			case 'docx':
				return $this->extract_docx_text( $file_path );

			default:
				return '';
		}
	}

	/**
	 * Extract text from a PDF file using smalot/pdfparser.
	 *
	 * Returns '' if the library is not installed (caller will surface install notice).
	 * Returns '__PDF_NO_TEXT__' sentinel if the PDF has no extractable readable text.
	 *
	 * @param string $file_path Absolute path to the PDF.
	 * @return string Extracted text, '' if library missing, '__PDF_NO_TEXT__' sentinel.
	 */
	private function extract_pdf_text( string $file_path ): string {
		require_once GRAYFOX_PATH . 'vendor/autoload.php';

		try {
			$parser = new \Smalot\PdfParser\Parser();
			$pdf    = $parser->parseFile( $file_path );
			$text   = $pdf->getText();
		} catch ( \Throwable $e ) {
			error_log( 'GrayFox PDF parse error: ' . $e->getMessage() );
			return '__PDF_NO_TEXT__';
		}

		// Sanity check: require at least 50 meaningful words.
		$words = preg_split( '/[\s\p{P}]+/u', $text, -1, PREG_SPLIT_NO_EMPTY );
		$meaningful = array_filter( $words, static fn( $w ) => mb_strlen( $w ) >= 2 );
		if ( count( $meaningful ) < 50 ) {
			return '__PDF_NO_TEXT__';
		}

		return $text;
	}

	/**
	 * Extract text from a DOCX file (ZIP-based XML format).
	 *
	 * @param string $file_path Absolute path to the DOCX.
	 * @return string Extracted text.
	 */
	private function extract_docx_text( string $file_path ): string {
		if ( ! class_exists( 'ZipArchive' ) ) {
			return '';
		}

		$zip = new ZipArchive();
		if ( true !== $zip->open( $file_path ) ) {
			return '';
		}

		$xml_content = $zip->getFromName( 'word/document.xml' );
		$zip->close();

		if ( false === $xml_content ) {
			return '';
		}

		$text = wp_strip_all_tags( $xml_content );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		return trim( $text );
	}

	/**
	 * Split a large document into overlapping chunks, summarize each, and merge results.
	 *
	 * Chunk size: 55k chars with 2k overlap so context isn't lost at boundaries.
	 * Merge strategy: for each topic key, append values from later chunks separated by "|".
	 * If all chunks fail, returns empty string.
	 *
	 * @param string $raw_text    Full document text (may be arbitrarily long).
	 * @param string $source_name Document name for error logging.
	 * @return string Merged valid JSON string, or empty string on total failure.
	 */
	private function summarize_chunked( string $raw_text, string $source_name ): string {
		$chunk_size = 55000;
		$overlap    = 2000;
		$chunks     = array();
		$offset     = 0;
		$length     = mb_strlen( $raw_text );

		while ( $offset < $length ) {
			$chunks[] = mb_substr( $raw_text, $offset, $chunk_size );
			$offset  += $chunk_size - $overlap;
		}

		$merged = array();
		$chunk_count = count( $chunks );

		foreach ( $chunks as $i => $chunk ) {
			$label  = $source_name . ' [chunk ' . ( $i + 1 ) . '/' . $chunk_count . ']';
			$result = $this->summarize_with_llm( $chunk, $label );

			if ( empty( $result ) ) {
				error_log( 'GrayFox RAG: chunk ' . ( $i + 1 ) . '/' . $chunk_count . ' failed for "' . $source_name . '"' );
				continue;
			}

			$decoded = json_decode( $result, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}

			foreach ( $decoded as $key => $value ) {
				if ( ! isset( $merged[ $key ] ) ) {
					$merged[ $key ] = $value;
				} else {
					// Append additional chunk values with "|" separator.
					$merged[ $key ] = $merged[ $key ] . '|' . $value;
				}
			}
		}

		if ( empty( $merged ) ) {
			return '';
		}

		return wp_json_encode( $merged );
	}

	/**
	 * Use the configured LLM to summarize document text into structured JSON.
	 *
	 * Uses request_json() with provider-level JSON enforcement and temperature=0
	 * for deterministic output. Does NOT fall back to raw text.
	 *
	 * @param string $raw_text    Raw extracted document text.
	 * @param string $source_name Document name for error logging.
	 * @return string Valid JSON string, or empty string on failure.
	 */
	private function summarize_with_llm( string $raw_text, string $source_name = '' ): string {
		$encrypted_key = get_option( 'grayfox_llm_api_key', '' );
		$api_key       = grayfox_decrypt( $encrypted_key );
		$provider      = get_option( 'grayfox_llm_provider', 'openai' );
		$model         = get_option( 'grayfox_llm_model', '' );

		if ( empty( $api_key ) || empty( $model ) ) {
			return '';
		}

		$system_prompt = GRAYFOX_PROMPT_RAG_SUMMARIZE;

		$messages = array(
			array( 'role' => 'system', 'content' => $system_prompt ),
			array( 'role' => 'user',   'content' => $raw_text ),
		);

		$llm    = new GrayFox_LLM();
		$result = $llm->request_json( $provider, $api_key, $model, $messages, 0.0 );

		if ( empty( $result ) ) {
			error_log( 'GrayFox summarize_with_llm: empty response for "' . $source_name . '" via ' . $provider );
			return '';
		}

		json_decode( $result );
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			error_log( 'GrayFox summarize_with_llm: JSON parse failed for "' . $source_name . '" via ' . $provider . '. Response: ' . mb_substr( $result, 0, 200 ) );
			return '';
		}

		return $result;
	}
}
