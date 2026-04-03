<?php
/**
 * Admin Knowledge Base page template.
 *
 * @package GrayFox
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! current_user_can( 'manage_options' ) ) {
	return;
}

global $wpdb;
$kb_table = GrayFox_DB::get_table( 'knowledge_base' );

// Fetch all knowledge base entries.
$entries = $wpdb->get_results(
	"SELECT id, source_type, source_id, source_name, content_json, token_estimate, last_processed_at, status, topic_index, created_at FROM `{$kb_table}` ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
	ARRAY_A
);

// Get tier doc limit.
$tier        = get_option( 'grayfox_license_tier', '' );
$tier_limits = array(
	'starter'   => 20,
	'growth'    => 100,
	'pro'       => 0,
	'beast_mode'=> 0,
	''          => 5,
);
$limit         = $tier_limits[ $tier ] ?? 5;
$count         = count( $entries );
$pending_count = count( array_filter( $entries, fn( $e ) => 'active' !== $e['status'] ) );
$ready_count   = $count - $pending_count;

// Pending conflicts.
$pending_conflicts = (array) get_option( 'grayfox_pending_conflicts', array() );

// Check for upload messages.
$uploaded   = isset( $_GET['uploaded'] ) && '1' === $_GET['uploaded']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error_code = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$error_messages = array(
	'no_file'             => __( 'No file was selected.', 'grayfox' ),
	'upload_failed'       => __( 'Upload failed. Please check the file type and size.', 'grayfox' ),
	'tier_limit'          => __( 'Document limit reached for your current plan. Please upgrade to add more documents.', 'grayfox' ),
);

// Onboarding — show once per first active document.
$show_onboarding = (bool) get_transient( 'grayfox_kb_first_doc_ready' );
if ( $show_onboarding ) {
	delete_transient( 'grayfox_kb_first_doc_ready' );
}

?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php esc_html_e( 'Knowledge Base', 'grayfox' ); ?></h1>

	<?php if ( $show_onboarding ) : ?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Your knowledge base is ready!', 'grayfox' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=grayfox-site-builder' ) ); ?>">
					<?php esc_html_e( 'Want GrayFox to build your site from it?', 'grayfox' ); ?>
				</a>
			</p>
		</div>
	<?php endif; ?>

	<?php foreach ( $pending_conflicts as $conflict ) : ?>
		<?php
		$new_id   = (int) ( $conflict['new_doc_id'] ?? 0 );
		$old_id   = (int) ( $conflict['old_doc_id'] ?? 0 );
		$new_name = esc_html( $conflict['new_source_name'] ?? "Doc #{$new_id}" );
		$old_name = esc_html( $conflict['old_source_name'] ?? "Doc #{$old_id}" );
		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				/* translators: 1: new document name, 2: old document name */
				printf(
					esc_html__( 'Conflict detected: "%1$s" overlaps with "%2$s".', 'grayfox' ),
					$new_name,
					$old_name
				);
				?>
				<a href="#grayfox-conflict-<?php echo esc_attr( "{$new_id}-{$old_id}" ); ?>" style="margin-left:8px;">
					<?php esc_html_e( 'Review Conflict', 'grayfox' ); ?>
				</a>
			</p>
		</div>
	<?php endforeach; ?>

	<?php if ( $uploaded ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Document uploaded and queued for processing.', 'grayfox' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $error_code ) && isset( $error_messages[ $error_code ] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error_messages[ $error_code ] ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Tier limit indicator -->
	<div class="grayfox-tier-indicator">
		<?php if ( 0 === $limit ) : ?>
			<p>
				<?php
				/* translators: %d: number of ready documents */
				printf( esc_html__( '%d document(s) ready — Unlimited', 'grayfox' ), $ready_count );
				if ( $pending_count > 0 ) {
					echo ' &nbsp;<em>' . esc_html( sprintf(
						/* translators: %d: number of pending documents */
						_n( '%d processing…', '%d processing…', $pending_count, 'grayfox' ),
						$pending_count
					) ) . '</em>';
				}
				?>
			</p>
		<?php else : ?>
			<p>
				<?php
				/* translators: 1: total count, 2: limit */
				printf( esc_html__( '%1$d of %2$d documents used', 'grayfox' ), $count, $limit );
				if ( $pending_count > 0 ) {
					echo ' &nbsp;<em>' . esc_html( sprintf(
						/* translators: %d: number of pending documents */
						_n( '(%d processing…)', '(%d processing…)', $pending_count, 'grayfox' ),
						$pending_count
					) ) . '</em>';
				}
				?>
			</p>
			<progress value="<?php echo esc_attr( $count ); ?>" max="<?php echo esc_attr( $limit ); ?>" style="width:200px;"></progress>
		<?php endif; ?>
	</div>

	<!-- Upload form -->
	<div class="grayfox-upload-form">
		<h2><?php esc_html_e( 'Upload Document', 'grayfox' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'grayfox_upload_document' ); ?>
			<input type="hidden" name="action" value="grayfox_upload_document" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="grayfox_document"><?php esc_html_e( 'Document File', 'grayfox' ); ?></label>
					</th>
					<td>
						<input type="file"
							   id="grayfox_document"
							   name="grayfox_document"
							   accept=".pdf,.docx,.txt,.csv,.md" />
						<p class="description">
							<?php esc_html_e( 'Accepted formats: PDF, DOCX, TXT, CSV, MD. Maximum 10MB.', 'grayfox' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php
			$at_limit    = ( $limit > 0 && $count >= $limit );
			$extra_attrs = $at_limit ? array( 'disabled' => 'disabled' ) : array();
			submit_button( __( 'Upload & Process', 'grayfox' ), 'primary', 'submit', true, $extra_attrs );
			?>
		</form>
	</div>

	<!-- Document list -->
	<h2><?php esc_html_e( 'Documents', 'grayfox' ); ?></h2>

	<?php if ( empty( $entries ) ) : ?>
		<p><?php esc_html_e( 'No documents yet. Upload your first document above.', 'grayfox' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped grayfox-kb-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Document Name', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Source Type', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Token Estimate', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Last Processed', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Status', 'grayfox' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'grayfox' ); ?></th>
				</tr>
			</thead>
			<tbody id="grayfox-kb-tbody">
				<?php foreach ( $entries as $entry ) :
					$topic_data     = ! empty( $entry['topic_index'] ) ? json_decode( $entry['topic_index'], true ) : array();
					$has_error      = is_array( $topic_data ) && isset( $topic_data['_error'] );
					$has_warning    = is_array( $topic_data ) && isset( $topic_data['_warning'] );
					$warning_msg    = '';
					if ( $has_warning ) {
						$warning_msg = 'pdf_no_text' === $topic_data['_warning']
							? __( 'PDF appears to be a scan or image-only — no extractable text found.', 'grayfox' )
							: __( 'PDF parsing library is not installed.', 'grayfox' );
					}
					$status_map = array(
						'active'         => array( 'label' => __( 'Active', 'grayfox' ), 'class' => 'grayfox-status--complete' ),
						'pending'        => array( 'label' => __( 'Pending', 'grayfox' ), 'class' => 'grayfox-status--pending' ),
						'pending_review' => array( 'label' => __( 'Review Required', 'grayfox' ), 'class' => 'grayfox-status--review' ),
					);
					$status_info = $status_map[ $entry['status'] ] ?? array( 'label' => esc_html( $entry['status'] ), 'class' => '' );
				?>
					<tr id="grayfox-kb-row-<?php echo esc_attr( $entry['id'] ); ?>">
						<td>
							<strong><?php echo esc_html( $entry['source_name'] ); ?></strong>
							<?php if ( $has_warning ) : ?>
								<span class="grayfox-warning-badge dashicons dashicons-warning"
									  title="<?php echo esc_attr( $warning_msg ); ?>"
									  style="color:#d63638;cursor:help;margin-left:4px;"></span>
							<?php endif; ?>
						</td>
						<td><?php echo esc_html( $entry['source_type'] ); ?></td>
						<td>
							<?php
							echo esc_html(
								! empty( $entry['token_estimate'] )
									? number_format_i18n( (int) $entry['token_estimate'] )
									: '—'
							);
							?>
						</td>
						<td>
							<?php
							echo esc_html(
								! empty( $entry['last_processed_at'] )
									? $entry['last_processed_at']
									: '—'
							);
							?>
						</td>
						<td>
							<span class="grayfox-status <?php echo esc_attr( $status_info['class'] ); ?>">
								<?php echo esc_html( $status_info['label'] ); ?>
							</span>
						</td>
						<td>
							<button type="button"
									class="button button-small grayfox-delete-doc"
									data-id="<?php echo esc_attr( $entry['id'] ); ?>"
									data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_delete_kb_document' ) ); ?>">
								<?php esc_html_e( 'Delete', 'grayfox' ); ?>
							</button>
							<?php if ( $has_error ) : ?>
								<button type="button"
										class="button button-small grayfox-retry-doc"
										data-id="<?php echo esc_attr( $entry['id'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_retry_kb_document' ) ); ?>"
										style="margin-left:4px;">
									<?php esc_html_e( 'Retry', 'grayfox' ); ?>
								</button>
							<?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>

	<?php if ( ! empty( $pending_conflicts ) ) : ?>
		<!-- Conflict resolution panels -->
		<h2><?php esc_html_e( 'Pending Conflicts', 'grayfox' ); ?></h2>
		<?php foreach ( $pending_conflicts as $conflict ) :
			$new_id   = (int) ( $conflict['new_doc_id'] ?? 0 );
			$old_id   = (int) ( $conflict['old_doc_id'] ?? 0 );
			if ( ! $new_id || ! $old_id ) continue;

			// Fetch content_json for both.
			$new_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $new_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$old_row = $wpdb->get_row( $wpdb->prepare( "SELECT source_name, content_json FROM `{$kb_table}` WHERE id = %d", $old_id ), ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			if ( ! $new_row || ! $old_row ) continue;
		?>
			<div class="grayfox-conflict-panel" id="grayfox-conflict-<?php echo esc_attr( "{$new_id}-{$old_id}" ); ?>">
				<h3>
					<?php
					/* translators: 1: new doc name, 2: old doc name */
					printf( esc_html__( 'Conflict: "%1$s" vs. "%2$s"', 'grayfox' ),
						esc_html( $new_row['source_name'] ?? "Doc #{$new_id}" ),
						esc_html( $old_row['source_name'] ?? "Doc #{$old_id}" )
					);
					?>
				</h3>

				<div style="display:flex;gap:16px;margin-bottom:12px;">
					<div style="flex:1;border:1px solid #ccc;padding:8px;background:#f9f9f9;max-height:200px;overflow-y:auto;">
						<strong><?php echo esc_html( $old_row['source_name'] ?? "Doc #{$old_id}" ); ?></strong>
						<pre style="white-space:pre-wrap;font-size:11px;"><?php echo esc_html( wp_trim_words( $old_row['content_json'] ?? '', 80 ) ); ?></pre>
					</div>
					<div style="flex:1;border:1px solid #ccc;padding:8px;background:#f9f9f9;max-height:200px;overflow-y:auto;">
						<strong><?php echo esc_html( $new_row['source_name'] ?? "Doc #{$new_id}" ); ?></strong>
						<pre style="white-space:pre-wrap;font-size:11px;"><?php echo esc_html( wp_trim_words( $new_row['content_json'] ?? '', 80 ) ); ?></pre>
					</div>
				</div>

				<div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
					<button type="button"
							class="button grayfox-resolve-conflict"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-resolution="keep_new"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_resolve_conflict' ) ); ?>">
						<?php esc_html_e( 'Keep New', 'grayfox' ); ?>
					</button>
					<button type="button"
							class="button grayfox-resolve-conflict"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-resolution="keep_old"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_resolve_conflict' ) ); ?>">
						<?php esc_html_e( 'Keep Old', 'grayfox' ); ?>
					</button>
					<button type="button"
							class="button grayfox-resolve-conflict"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-resolution="keep_both"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_resolve_conflict' ) ); ?>">
						<?php esc_html_e( 'Keep Both', 'grayfox' ); ?>
					</button>
					<button type="button"
							class="button button-secondary grayfox-get-diff"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_get_conflict_diff' ) ); ?>">
						<?php esc_html_e( 'Load AI Diff', 'grayfox' ); ?>
					</button>
					<span class="grayfox-diff-result" id="grayfox-diff-<?php echo esc_attr( "{$new_id}-{$old_id}" ); ?>" style="font-style:italic;font-size:13px;margin-left:4px;"></span>
				</div>
			</div>
		<?php endforeach; ?>
	<?php endif; ?>
</div>

<script>
(function() {
	var ajaxUrl = <?php echo wp_json_encode( admin_url( 'admin-ajax.php' ) ); ?>;

	// ---- Delete document ----
	document.querySelectorAll('.grayfox-delete-doc').forEach(function(btn) {
		btn.addEventListener('click', function() {
			if (!confirm(<?php echo wp_json_encode( __( 'Delete this document? This cannot be undone.', 'grayfox' ) ); ?>)) return;
			var id = this.dataset.id;
			var nonce = this.dataset.nonce;
			var row = document.getElementById('grayfox-kb-row-' + id);
			btn.disabled = true;

			var data = new FormData();
			data.append('action', 'grayfox_delete_kb_document');
			data.append('id', id);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {method:'POST', body:data})
				.then(function(r){return r.json();})
				.then(function(resp){
					if (resp.success) {
						if (row) row.remove();
					} else {
						alert(resp.data || <?php echo wp_json_encode( __( 'Delete failed.', 'grayfox' ) ); ?>);
						btn.disabled = false;
					}
				})
				.catch(function(){
					alert(<?php echo wp_json_encode( __( 'Network error.', 'grayfox' ) ); ?>);
					btn.disabled = false;
				});
		});
	});

	// ---- Retry document ----
	document.querySelectorAll('.grayfox-retry-doc').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var id = this.dataset.id;
			var nonce = this.dataset.nonce;
			btn.disabled = true;
			btn.textContent = <?php echo wp_json_encode( __( 'Queuing…', 'grayfox' ) ); ?>;

			var data = new FormData();
			data.append('action', 'grayfox_retry_kb_document');
			data.append('id', id);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {method:'POST', body:data})
				.then(function(r){return r.json();})
				.then(function(resp){
					if (resp.success) {
						btn.textContent = <?php echo wp_json_encode( __( 'Queued!', 'grayfox' ) ); ?>;
					} else {
						alert(resp.data || <?php echo wp_json_encode( __( 'Retry failed.', 'grayfox' ) ); ?>);
						btn.disabled = false;
						btn.textContent = <?php echo wp_json_encode( __( 'Retry', 'grayfox' ) ); ?>;
					}
				});
		});
	});

	// ---- Resolve conflict ----
	document.querySelectorAll('.grayfox-resolve-conflict').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var newId = this.dataset.newId;
			var oldId = this.dataset.oldId;
			var resolution = this.dataset.resolution;
			var nonce = this.dataset.nonce;
			var panel = document.getElementById('grayfox-conflict-' + newId + '-' + oldId);

			if (!confirm(<?php echo wp_json_encode( __( 'Apply this resolution? This cannot be undone.', 'grayfox' ) ); ?>)) return;
			btn.disabled = true;

			var data = new FormData();
			data.append('action', 'grayfox_resolve_conflict');
			data.append('new_doc_id', newId);
			data.append('old_doc_id', oldId);
			data.append('resolution', resolution);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {method:'POST', body:data})
				.then(function(r){return r.json();})
				.then(function(resp){
					if (resp.success) {
						if (panel) panel.remove();
						window.location.reload();
					} else {
						alert(resp.data || <?php echo wp_json_encode( __( 'Resolution failed.', 'grayfox' ) ); ?>);
						btn.disabled = false;
					}
				});
		});
	});

	// ---- Load AI diff ----
	document.querySelectorAll('.grayfox-get-diff').forEach(function(btn) {
		btn.addEventListener('click', function() {
			var newId = this.dataset.newId;
			var oldId = this.dataset.oldId;
			var nonce = this.dataset.nonce;
			var result = document.getElementById('grayfox-diff-' + newId + '-' + oldId);
			btn.disabled = true;
			if (result) result.textContent = <?php echo wp_json_encode( __( 'Loading…', 'grayfox' ) ); ?>;

			var data = new FormData();
			data.append('action', 'grayfox_get_conflict_diff');
			data.append('new_doc_id', newId);
			data.append('old_doc_id', oldId);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {method:'POST', body:data})
				.then(function(r){return r.json();})
				.then(function(resp){
					btn.disabled = false;
					if (resp.success && resp.data) {
						if (result) result.textContent = resp.data.diff || '';
					} else {
						if (result) result.textContent = resp.data || <?php echo wp_json_encode( __( 'Failed to load diff.', 'grayfox' ) ); ?>;
					}
				})
				.catch(function(){
					btn.disabled = false;
					if (result) result.textContent = <?php echo wp_json_encode( __( 'Network error.', 'grayfox' ) ); ?>;
				});
		});
	});
})();
</script>
