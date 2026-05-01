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
$kb_table = esc_sql( GrayFox_DB::get_table( 'knowledge_base' ) );

// Fetch all knowledge base entries.
$entries = $wpdb->get_results(
	"SELECT id, source_type, source_id, source_name, content_json, token_estimate, last_processed_at, status, topic_index, created_at FROM `{$kb_table}` ORDER BY created_at DESC", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.NoCaching
	ARRAY_A
);

$count         = count( $entries );
$pending_count = count( array_filter( $entries, fn( $e ) => 'active' !== $e['status'] ) );
$ready_count   = $count - $pending_count;

// Pending conflicts.
$pending_conflicts = (array) get_option( 'grayfox_pending_conflicts', array() );

// Check for upload messages.
$uploaded   = isset( $_GET['uploaded'] ) && '1' === $_GET['uploaded']; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$error_code = isset( $_GET['error'] ) ? sanitize_text_field( wp_unslash( $_GET['error'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

$error_messages = array(
	'no_file'      => __( 'No file was selected.', 'kbfox' ),
	'upload_failed' => __( 'Upload failed. Please check the file type and size.', 'kbfox' ),
);

// Onboarding — show once per first active document.
$show_onboarding = (bool) get_transient( 'grayfox_kb_first_doc_ready' );
if ( $show_onboarding ) {
	delete_transient( 'grayfox_kb_first_doc_ready' );
}

?>
<div class="wrap grayfox-admin-wrap">
	<h1><?php esc_html_e( 'Knowledge Base', 'kbfox' ); ?></h1>

	<?php if ( $show_onboarding ) : ?>
		<div class="notice notice-info is-dismissible">
			<p>
				<?php esc_html_e( 'Your knowledge base is ready!', 'kbfox' ); ?>
				<a href="https://plugins.grayfoxdc.com" target="_blank" rel="noopener noreferrer">
					<?php esc_html_e( 'Want to build your site from it? Check out GrayFox Pro.', 'kbfox' ); ?>
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
				printf(
					/* translators: 1: new document name, 2: old document name */
					esc_html__( 'Conflict detected: "%1$s" overlaps with "%2$s".', 'kbfox' ),
					esc_html( $new_name ),
					esc_html( $old_name )
				);
				?>
				<a href="#grayfox-conflict-<?php echo esc_attr( "{$new_id}-{$old_id}" ); ?>" style="margin-left:8px;">
					<?php esc_html_e( 'Review Conflict', 'kbfox' ); ?>
				</a>
			</p>
		</div>
	<?php endforeach; ?>

	<?php if ( $uploaded ) : ?>
		<div class="notice notice-success is-dismissible">
			<p><?php esc_html_e( 'Document uploaded and queued for processing.', 'kbfox' ); ?></p>
		</div>
	<?php endif; ?>

	<?php if ( ! empty( $error_code ) && isset( $error_messages[ $error_code ] ) ) : ?>
		<div class="notice notice-error is-dismissible">
			<p><?php echo esc_html( $error_messages[ $error_code ] ); ?></p>
		</div>
	<?php endif; ?>

	<!-- Upload form -->
	<div class="grayfox-upload-form">
		<h2><?php esc_html_e( 'Upload Document', 'kbfox' ); ?></h2>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" enctype="multipart/form-data">
			<?php wp_nonce_field( 'grayfox_upload_document' ); ?>
			<input type="hidden" name="action" value="grayfox_upload_document" />

			<table class="form-table">
				<tr>
					<th scope="row">
						<label for="grayfox_document"><?php esc_html_e( 'Document File', 'kbfox' ); ?></label>
					</th>
					<td>
						<input type="file"
							   id="grayfox_document"
							   name="grayfox_document"
							   accept=".pdf,.docx,.txt,.csv,.md" />
						<p class="description">
							<?php esc_html_e( 'Accepted formats: PDF, DOCX, TXT, CSV, MD. Maximum 10MB.', 'kbfox' ); ?>
						</p>
					</td>
				</tr>
			</table>

			<?php submit_button( __( 'Upload & Process', 'kbfox' ) ); ?>
		</form>
	</div>

	<!-- Document list -->
	<h2><?php esc_html_e( 'Documents', 'kbfox' ); ?></h2>

	<?php if ( empty( $entries ) ) : ?>
		<p><?php esc_html_e( 'No documents yet. Upload your first document above.', 'kbfox' ); ?></p>
	<?php else : ?>
		<table class="wp-list-table widefat fixed striped grayfox-kb-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Document Name', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Source Type', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Token Estimate', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Last Processed', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Status', 'kbfox' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'kbfox' ); ?></th>
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
							? __( 'PDF appears to be a scan or image-only — no extractable text found.', 'kbfox' )
							: __( 'PDF parsing library is not installed.', 'kbfox' );
					}
					$status_map = array(
						'active'         => array( 'label' => __( 'Active', 'kbfox' ), 'class' => 'grayfox-status--complete' ),
						'pending'        => array( 'label' => __( 'Pending', 'kbfox' ), 'class' => 'grayfox-status--pending' ),
						'pending_review' => array( 'label' => __( 'Review Required', 'kbfox' ), 'class' => 'grayfox-status--review' ),
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
								<?php esc_html_e( 'Delete', 'kbfox' ); ?>
							</button>
							<?php if ( $has_error ) : ?>
								<button type="button"
										class="button button-small grayfox-retry-doc"
										data-id="<?php echo esc_attr( $entry['id'] ); ?>"
										data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_retry_kb_document' ) ); ?>"
										style="margin-left:4px;">
									<?php esc_html_e( 'Retry', 'kbfox' ); ?>
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
		<h2><?php esc_html_e( 'Pending Conflicts', 'kbfox' ); ?></h2>
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
					printf( esc_html__( 'Conflict: "%1$s" vs. "%2$s"', 'kbfox' ),
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
						<?php esc_html_e( 'Keep New', 'kbfox' ); ?>
					</button>
					<button type="button"
							class="button grayfox-resolve-conflict"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-resolution="keep_old"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_resolve_conflict' ) ); ?>">
						<?php esc_html_e( 'Keep Old', 'kbfox' ); ?>
					</button>
					<button type="button"
							class="button grayfox-resolve-conflict"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-resolution="keep_both"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_resolve_conflict' ) ); ?>">
						<?php esc_html_e( 'Keep Both', 'kbfox' ); ?>
					</button>
					<button type="button"
							class="button button-secondary grayfox-get-diff"
							data-new-id="<?php echo esc_attr( $new_id ); ?>"
							data-old-id="<?php echo esc_attr( $old_id ); ?>"
							data-nonce="<?php echo esc_attr( wp_create_nonce( 'grayfox_get_conflict_diff' ) ); ?>">
						<?php esc_html_e( 'Load AI Diff', 'kbfox' ); ?>
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
			if (!confirm(<?php echo wp_json_encode( __( 'Delete this document? This cannot be undone.', 'kbfox' ) ); ?>)) return;
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
						alert(resp.data || <?php echo wp_json_encode( __( 'Delete failed.', 'kbfox' ) ); ?>);
						btn.disabled = false;
					}
				})
				.catch(function(){
					alert(<?php echo wp_json_encode( __( 'Network error.', 'kbfox' ) ); ?>);
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
			btn.textContent = <?php echo wp_json_encode( __( 'Queuing…', 'kbfox' ) ); ?>;

			var data = new FormData();
			data.append('action', 'grayfox_retry_kb_document');
			data.append('id', id);
			data.append('_ajax_nonce', nonce);

			fetch(ajaxUrl, {method:'POST', body:data})
				.then(function(r){return r.json();})
				.then(function(resp){
					if (resp.success) {
						btn.textContent = <?php echo wp_json_encode( __( 'Queued!', 'kbfox' ) ); ?>;
					} else {
						alert(resp.data || <?php echo wp_json_encode( __( 'Retry failed.', 'kbfox' ) ); ?>);
						btn.disabled = false;
						btn.textContent = <?php echo wp_json_encode( __( 'Retry', 'kbfox' ) ); ?>;
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

			if (!confirm(<?php echo wp_json_encode( __( 'Apply this resolution? This cannot be undone.', 'kbfox' ) ); ?>)) return;
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
						alert(resp.data || <?php echo wp_json_encode( __( 'Resolution failed.', 'kbfox' ) ); ?>);
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
			if (result) result.textContent = <?php echo wp_json_encode( __( 'Loading…', 'kbfox' ) ); ?>;

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
						if (result) result.textContent = resp.data || <?php echo wp_json_encode( __( 'Failed to load diff.', 'kbfox' ) ); ?>;
					}
				})
				.catch(function(){
					btn.disabled = false;
					if (result) result.textContent = <?php echo wp_json_encode( __( 'Network error.', 'kbfox' ) ); ?>;
				});
		});
	});
})();
</script>
