<?php
/**
 * Admin UI: settings page, submissions list, single submission view + responses.
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_Admin {

	const CAP        = 'manage_options';
	const PAGE_SLUG  = 'fourl-feedback';
	const SETTINGS_GROUP = 'fourl_feedback_settings_group';

	public function __construct() {
		add_action( 'admin_menu',  array( $this, 'register_menu' ) );
		add_action( 'admin_init',  array( $this, 'register_settings' ) );
		add_action( 'admin_init',  array( $this, 'handle_admin_actions' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_assets' ) );
	}

	public function register_menu() {
		$count_new = FourL_Feedback_DB::count_submissions( 'new' );
		$badge     = $count_new ? ' <span class="awaiting-mod">' . esc_html( $count_new ) . '</span>' : '';

		add_menu_page(
			__( '4L Feedback', '4lfeedback' ),
			__( '4L Feedback', '4lfeedback' ) . $badge,
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_submissions_page' ),
			'dashicons-format-chat',
			30
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( 'Submissions', '4lfeedback' ),
			__( 'Submissions', '4lfeedback' ),
			self::CAP,
			self::PAGE_SLUG,
			array( $this, 'render_submissions_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( '4L Feedback Settings', '4lfeedback' ),
			__( 'Settings', '4lfeedback' ),
			self::CAP,
			self::PAGE_SLUG . '-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			self::PAGE_SLUG,
			__( '4L Feedback Logs', '4lfeedback' ),
			__( 'Logs', '4lfeedback' ),
			self::CAP,
			self::PAGE_SLUG . '-logs',
			array( $this, 'render_logs_page' )
		);
	}

	public function enqueue_admin_assets( $hook ) {
		if ( false === strpos( (string) $hook, self::PAGE_SLUG ) ) {
			return;
		}
		wp_enqueue_style( 'fourl-feedback' );
	}

	public function register_settings() {
		register_setting(
			self::SETTINGS_GROUP,
			FourL_Feedback_Plugin::OPTION_SETTINGS,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array(),
			)
		);
	}

	public function sanitize_settings( $input ) {
		$out = array();
		$out['notification_email'] = isset( $input['notification_email'] ) ? sanitize_email( $input['notification_email'] ) : '';
		if ( ! is_email( $out['notification_email'] ) ) {
			$out['notification_email'] = get_option( 'admin_email' );
		}
		$out['require_email']   = ! empty( $input['require_email'] ) ? 1 : 0;
		$out['allow_anonymous'] = ! empty( $input['allow_anonymous'] ) ? 1 : 0;
		$out['enable_logging']  = ! empty( $input['enable_logging'] ) ? 1 : 0;
		return $out;
	}

	public function handle_admin_actions() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		if ( empty( $_GET['page'] ) || self::PAGE_SLUG !== $_GET['page'] ) {
			return;
		}

		if ( isset( $_POST['fourl_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['fourl_action'] ) );

			if ( 'add_response' === $action ) {
				check_admin_referer( 'fourl_add_response' );
				$submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
				$body          = isset( $_POST['response_body'] ) ? wp_kses_post( wp_unslash( $_POST['response_body'] ) ) : '';
				$is_public     = ! empty( $_POST['is_public'] );
				if ( $submission_id && '' !== trim( wp_strip_all_tags( $body ) ) ) {
					FourL_Feedback_DB::insert_response( $submission_id, $body, get_current_user_id(), $is_public );
					FourL_Feedback_DB::update_submission_status( $submission_id, 'reviewed' );
					$this->redirect_with_flash( 'response_added', $submission_id );
				}
			}

			if ( 'delete_response' === $action ) {
				check_admin_referer( 'fourl_delete_response' );
				$response_id   = isset( $_POST['response_id'] ) ? (int) $_POST['response_id'] : 0;
				$submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
				if ( $response_id ) {
					FourL_Feedback_DB::delete_response( $response_id );
					$this->redirect_with_flash( 'response_deleted', $submission_id );
				}
			}

			if ( 'update_status' === $action ) {
				check_admin_referer( 'fourl_update_status' );
				$submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
				$status        = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
				if ( $submission_id && $status ) {
					FourL_Feedback_DB::update_submission_status( $submission_id, $status );
					$this->redirect_with_flash( 'status_updated', $submission_id );
				}
			}

			if ( 'delete_submission' === $action ) {
				check_admin_referer( 'fourl_delete_submission' );
				$submission_id = isset( $_POST['submission_id'] ) ? (int) $_POST['submission_id'] : 0;
				if ( $submission_id ) {
					FourL_Feedback_DB::delete_submission( $submission_id );
					$this->redirect_with_flash( 'submission_deleted' );
				}
			}
		}

		if ( ! empty( $_GET['page'] ) && self::PAGE_SLUG . '-logs' === $_GET['page'] && isset( $_POST['fourl_action'] ) ) {
			$action = sanitize_key( wp_unslash( $_POST['fourl_action'] ) );
			if ( 'clear_logs' === $action ) {
				check_admin_referer( 'fourl_clear_logs' );
				FourL_Feedback_Logger::clear();
				wp_safe_redirect( add_query_arg( array( 'page' => self::PAGE_SLUG . '-logs', 'flash' => 'logs_cleared' ), admin_url( 'admin.php' ) ) );
				exit;
			}
		}
	}

	private function redirect_with_flash( $flash, $submission_id = 0 ) {
		$args = array(
			'page'  => self::PAGE_SLUG,
			'flash' => $flash,
		);
		if ( $submission_id ) {
			$args['view'] = $submission_id;
		}
		wp_safe_redirect( add_query_arg( $args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function render_submissions_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$flash = isset( $_GET['flash'] ) ? sanitize_key( wp_unslash( $_GET['flash'] ) ) : '';
		if ( $flash ) {
			$messages = array(
				'response_added'     => __( 'Response added.', '4lfeedback' ),
				'response_deleted'   => __( 'Response deleted.', '4lfeedback' ),
				'status_updated'     => __( 'Status updated.', '4lfeedback' ),
				'submission_deleted' => __( 'Submission deleted.', '4lfeedback' ),
			);
			if ( isset( $messages[ $flash ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $flash ] ) . '</p></div>';
			}
		}

		$view_id = isset( $_GET['view'] ) ? (int) $_GET['view'] : 0;
		if ( $view_id ) {
			$this->render_single_submission( $view_id );
			return;
		}
		$this->render_submissions_list();
	}

	private function render_submissions_list() {
		$status_filter = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$paged         = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page      = 20;
		$offset        = ( $paged - 1 ) * $per_page;

		$rows  = FourL_Feedback_DB::get_submissions(
			array(
				'status' => $status_filter,
				'limit'  => $per_page,
				'offset' => $offset,
			)
		);
		$total = FourL_Feedback_DB::count_submissions( $status_filter );
		$pages = max( 1, (int) ceil( $total / $per_page ) );

		$counts = array(
			''         => FourL_Feedback_DB::count_submissions(),
			'new'      => FourL_Feedback_DB::count_submissions( 'new' ),
			'reviewed' => FourL_Feedback_DB::count_submissions( 'reviewed' ),
			'actioned' => FourL_Feedback_DB::count_submissions( 'actioned' ),
			'archived' => FourL_Feedback_DB::count_submissions( 'archived' ),
		);

		$base_url = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( '4L Feedback Submissions', '4lfeedback' ); ?></h1>
			<hr class="wp-header-end">

			<ul class="subsubsub">
				<?php
				$labels = array(
					''         => __( 'All', '4lfeedback' ),
					'new'      => __( 'New', '4lfeedback' ),
					'reviewed' => __( 'Reviewed', '4lfeedback' ),
					'actioned' => __( 'Actioned', '4lfeedback' ),
					'archived' => __( 'Archived', '4lfeedback' ),
				);
				$keys = array_keys( $labels );
				foreach ( $keys as $idx => $key ) {
					$class = $status_filter === $key ? 'class="current"' : '';
					$url   = $key ? add_query_arg( 'status', $key, $base_url ) : $base_url;
					$sep   = ( $idx < count( $keys ) - 1 ) ? ' |' : '';
					printf(
						'<li><a href="%1$s" %2$s>%3$s <span class="count">(%4$d)</span></a>%5$s</li>',
						esc_url( $url ),
						$class, // already-escaped attribute string
						esc_html( $labels[ $key ] ),
						(int) $counts[ $key ],
						esc_html( $sep )
					);
				}
				?>
			</ul>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Title', '4lfeedback' ); ?></th>
						<th><?php esc_html_e( 'Submitter', '4lfeedback' ); ?></th>
						<th><?php esc_html_e( 'Counts', '4lfeedback' ); ?></th>
						<th><?php esc_html_e( 'Status', '4lfeedback' ); ?></th>
						<th><?php esc_html_e( 'Submitted', '4lfeedback' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="5"><?php esc_html_e( 'No submissions yet.', '4lfeedback' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$view_url = add_query_arg(
								array(
									'page' => self::PAGE_SLUG,
									'view' => (int) $row['id'],
								),
								admin_url( 'admin.php' )
							);
							$counts_str = sprintf(
								'L:%d / Loa:%d / Lon:%d / Lea:%d',
								count( $row['items']['loved'] ?? array() ),
								count( $row['items']['loathed'] ?? array() ),
								count( $row['items']['longed'] ?? array() ),
								count( $row['items']['learned'] ?? array() )
							);
							?>
							<tr>
								<td>
									<strong><a href="<?php echo esc_url( $view_url ); ?>"><?php echo esc_html( $row['title'] ? $row['title'] : __( '(no title)', '4lfeedback' ) ); ?></a></strong>
								</td>
								<td>
									<?php echo esc_html( $row['submitter_name'] ? $row['submitter_name'] : __( 'Anonymous', '4lfeedback' ) ); ?>
									<?php if ( $row['submitter_email'] ) : ?>
										<br><a href="mailto:<?php echo esc_attr( $row['submitter_email'] ); ?>"><?php echo esc_html( $row['submitter_email'] ); ?></a>
									<?php endif; ?>
								</td>
								<td><code><?php echo esc_html( $counts_str ); ?></code></td>
								<td><?php echo esc_html( ucfirst( $row['status'] ) ); ?></td>
								<td><?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row['created_at'] ) ); ?></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					$page_links = paginate_links(
						array(
							'base'      => add_query_arg( 'paged', '%#%', $base_url . ( $status_filter ? '&status=' . $status_filter : '' ) ),
							'format'    => '',
							'prev_text' => '&laquo;',
							'next_text' => '&raquo;',
							'total'     => $pages,
							'current'   => $paged,
						)
					);
					echo wp_kses_post( $page_links );
					?>
				</div></div>
			<?php endif; ?>
		</div>
		<?php
	}

	private function render_single_submission( $id ) {
		$submission = FourL_Feedback_DB::get_submission( $id );
		if ( ! $submission ) {
			echo '<div class="wrap"><h1>' . esc_html__( 'Submission not found', '4lfeedback' ) . '</h1></div>';
			return;
		}

		$responses = FourL_Feedback_DB::get_responses_for( $id, false );
		$quadrants = FourL_Feedback_Shortcodes::quadrants();
		$back_url  = admin_url( 'admin.php?page=' . self::PAGE_SLUG );
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline">
				<?php echo esc_html( $submission['title'] ? $submission['title'] : __( '4L Submission', '4lfeedback' ) ); ?>
				<span style="font-size: 13px; color: #666; margin-left: 8px;">#<?php echo (int) $submission['id']; ?></span>
			</h1>
			<a href="<?php echo esc_url( $back_url ); ?>" class="page-title-action"><?php esc_html_e( '← Back to list', '4lfeedback' ); ?></a>
			<hr class="wp-header-end">

			<div style="display: grid; grid-template-columns: 2fr 1fr; gap: 20px; margin-top: 20px;">
				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php esc_html_e( 'Feedback', '4lfeedback' ); ?></h2></div>
						<div class="inside">
							<div class="fourl-quadrant-grid">
								<?php foreach ( $quadrants as $key => $q ) : ?>
									<?php $items = $submission['items'][ $key ] ?? array(); ?>
									<div class="fourl-quad fourl-quad-<?php echo esc_attr( $key ); ?>">
										<div class="fourl-quad-header">
											<h3 class="fourl-quad-title"><?php echo esc_html( $q['label'] ); ?></h3>
											<span class="fourl-quad-count"><?php echo (int) count( $items ); ?></span>
										</div>
										<p class="fourl-quad-subtitle"><?php echo esc_html( $q['subtitle'] ); ?></p>
										<?php if ( empty( $items ) ) : ?>
											<p style="font-size: 12px; color: #888; font-style: italic;"><?php esc_html_e( 'No items.', '4lfeedback' ); ?></p>
										<?php else : ?>
											<ul class="fourl-quad-items">
												<?php foreach ( $items as $item ) : ?>
													<li class="<?php echo ! empty( $item['starred'] ) ? 'fourl-starred' : ''; ?>">
														<?php if ( ! empty( $item['starred'] ) ) : ?><span class="fourl-star" aria-hidden="true">★</span><?php endif; ?>
														<span class="fourl-item-text"><?php echo esc_html( $item['text'] ); ?></span>
													</li>
												<?php endforeach; ?>
											</ul>
										<?php endif; ?>
									</div>
								<?php endforeach; ?>
							</div>
						</div>
					</div>

					<div class="postbox">
						<div class="postbox-header"><h2><?php esc_html_e( 'Responses', '4lfeedback' ); ?></h2></div>
						<div class="inside">
							<?php if ( empty( $responses ) ) : ?>
								<p><em><?php esc_html_e( 'No responses yet.', '4lfeedback' ); ?></em></p>
							<?php else : ?>
								<?php foreach ( $responses as $r ) : ?>
									<div style="background: #f6f7f9; border-radius: 6px; padding: 12px; margin-bottom: 10px;">
										<div style="font-size: 12px; color: #666; margin-bottom: 6px;">
											<?php
											$author = $r['author_id'] ? get_user_by( 'id', (int) $r['author_id'] ) : null;
											$name   = $author ? $author->display_name : __( 'Admin', '4lfeedback' );
											echo esc_html( $name . ' • ' . mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $r['created_at'] ) );
											echo $r['is_public'] ? ' • ' . esc_html__( 'Public', '4lfeedback' ) : ' • ' . esc_html__( 'Internal', '4lfeedback' );
											?>
										</div>
										<div><?php echo wp_kses_post( wpautop( $r['response_body'] ) ); ?></div>
										<form method="post" style="margin-top: 6px;" onsubmit="return confirm('<?php echo esc_js( __( 'Delete this response?', '4lfeedback' ) ); ?>');">
											<?php wp_nonce_field( 'fourl_delete_response' ); ?>
											<input type="hidden" name="fourl_action" value="delete_response">
											<input type="hidden" name="response_id" value="<?php echo (int) $r['id']; ?>">
											<input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
											<button type="submit" class="button-link-delete"><?php esc_html_e( 'Delete', '4lfeedback' ); ?></button>
										</form>
									</div>
								<?php endforeach; ?>
							<?php endif; ?>

							<h3 style="margin-top: 20px;"><?php esc_html_e( 'Add a response', '4lfeedback' ); ?></h3>
							<form method="post">
								<?php wp_nonce_field( 'fourl_add_response' ); ?>
								<input type="hidden" name="fourl_action" value="add_response">
								<input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
								<p>
									<textarea name="response_body" rows="5" style="width: 100%;" placeholder="<?php esc_attr_e( 'Write a response — actions taken, decisions, follow-ups…', '4lfeedback' ); ?>"></textarea>
								</p>
								<p>
									<label><input type="checkbox" name="is_public" value="1" checked> <?php esc_html_e( 'Show publicly via [fourl_feedback_responses] / [fourl_feedback_breadcrumbs] shortcodes', '4lfeedback' ); ?></label>
								</p>
								<p>
									<button type="submit" class="button button-primary"><?php esc_html_e( 'Add response', '4lfeedback' ); ?></button>
								</p>
							</form>
						</div>
					</div>
				</div>

				<div>
					<div class="postbox">
						<div class="postbox-header"><h2><?php esc_html_e( 'Details', '4lfeedback' ); ?></h2></div>
						<div class="inside">
							<p><strong><?php esc_html_e( 'Submitter:', '4lfeedback' ); ?></strong><br>
								<?php echo esc_html( $submission['submitter_name'] ? $submission['submitter_name'] : __( 'Anonymous', '4lfeedback' ) ); ?>
							</p>
							<?php if ( $submission['submitter_email'] ) : ?>
								<p><strong><?php esc_html_e( 'Email:', '4lfeedback' ); ?></strong><br>
									<a href="mailto:<?php echo esc_attr( $submission['submitter_email'] ); ?>"><?php echo esc_html( $submission['submitter_email'] ); ?></a>
								</p>
							<?php endif; ?>
							<p><strong><?php esc_html_e( 'Submitted:', '4lfeedback' ); ?></strong><br>
								<?php echo esc_html( mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $submission['created_at'] ) ); ?>
							</p>

							<form method="post" style="margin-top: 16px;">
								<?php wp_nonce_field( 'fourl_update_status' ); ?>
								<input type="hidden" name="fourl_action" value="update_status">
								<input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
								<label for="fourl-status"><strong><?php esc_html_e( 'Status', '4lfeedback' ); ?></strong></label><br>
								<select id="fourl-status" name="status" style="width: 100%;">
									<?php
									$statuses = array(
										'new'      => __( 'New', '4lfeedback' ),
										'reviewed' => __( 'Reviewed', '4lfeedback' ),
										'actioned' => __( 'Actioned', '4lfeedback' ),
										'archived' => __( 'Archived', '4lfeedback' ),
									);
									foreach ( $statuses as $key => $label ) {
										printf(
											'<option value="%1$s" %2$s>%3$s</option>',
											esc_attr( $key ),
											selected( $submission['status'], $key, false ),
											esc_html( $label )
										);
									}
									?>
								</select>
								<p><button type="submit" class="button"><?php esc_html_e( 'Update status', '4lfeedback' ); ?></button></p>
							</form>

							<hr>

							<form method="post" onsubmit="return confirm('<?php echo esc_js( __( 'Permanently delete this submission and all responses?', '4lfeedback' ) ); ?>');">
								<?php wp_nonce_field( 'fourl_delete_submission' ); ?>
								<input type="hidden" name="fourl_action" value="delete_submission">
								<input type="hidden" name="submission_id" value="<?php echo (int) $submission['id']; ?>">
								<button type="submit" class="button button-link-delete"><?php esc_html_e( 'Delete submission', '4lfeedback' ); ?></button>
							</form>
						</div>
					</div>
				</div>
			</div>
		</div>
		<?php
	}

	public function render_settings_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}
		$settings = FourL_Feedback_Plugin::get_settings();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( '4L Feedback Settings', '4lfeedback' ); ?></h1>

			<form method="post" action="options.php">
				<?php settings_fields( self::SETTINGS_GROUP ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="fourl-notification-email"><?php esc_html_e( 'Notification email', '4lfeedback' ); ?></label></th>
						<td>
							<input
								type="email"
								id="fourl-notification-email"
								name="<?php echo esc_attr( FourL_Feedback_Plugin::OPTION_SETTINGS ); ?>[notification_email]"
								value="<?php echo esc_attr( $settings['notification_email'] ); ?>"
								class="regular-text"
								required
							>
							<p class="description"><?php esc_html_e( 'Where new feedback notifications are sent.', '4lfeedback' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Submitter requirements', '4lfeedback' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( FourL_Feedback_Plugin::OPTION_SETTINGS ); ?>[allow_anonymous]" value="1" <?php checked( ! empty( $settings['allow_anonymous'] ) ); ?>>
								<?php esc_html_e( 'Allow anonymous submissions (name not required).', '4lfeedback' ); ?>
							</label>
							<br>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( FourL_Feedback_Plugin::OPTION_SETTINGS ); ?>[require_email]" value="1" <?php checked( ! empty( $settings['require_email'] ) ); ?>>
								<?php esc_html_e( 'Require submitter email.', '4lfeedback' ); ?>
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Logging', '4lfeedback' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr( FourL_Feedback_Plugin::OPTION_SETTINGS ); ?>[enable_logging]" value="1" <?php checked( ! empty( $settings['enable_logging'] ) ); ?>>
								<?php esc_html_e( 'Enable verbose logging (info/debug events).', '4lfeedback' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Warnings and errors are always logged. Turn this on to also capture every submission attempt — useful when diagnosing missing submissions. View logs under 4L Feedback → Logs.', '4lfeedback' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<hr>

			<h2><?php esc_html_e( 'Shortcodes', '4lfeedback' ); ?></h2>
			<table class="widefat striped" style="max-width: 800px;">
				<thead><tr><th><?php esc_html_e( 'Shortcode', '4lfeedback' ); ?></th><th><?php esc_html_e( 'Description', '4lfeedback' ); ?></th></tr></thead>
				<tbody>
					<tr>
						<td><code>[fourl_feedback_form]</code></td>
						<td><?php esc_html_e( 'Renders the 4Ls submission form. Attributes: title_label, title_placeholder, feedback_heading, submit_label, show_name, show_email, show_title, show_breadcrumbs (yes/no), breadcrumbs_limit.', '4lfeedback' ); ?></td>
					</tr>
					<tr>
						<td><code>[fourl_feedback_responses]</code></td>
						<td><?php esc_html_e( 'Lists the logged-in user\'s public admin responses. Attributes: limit, show_title.', '4lfeedback' ); ?></td>
					</tr>
					<tr>
						<td><code>[fourl_feedback_breadcrumbs]</code></td>
						<td><?php esc_html_e( 'Lists the logged-in user\'s submitted feedback. Attributes: limit, status, show_items, show_name.', '4lfeedback' ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function render_logs_page() {
		if ( ! current_user_can( self::CAP ) ) {
			return;
		}

		$flash = isset( $_GET['flash'] ) ? sanitize_key( wp_unslash( $_GET['flash'] ) ) : '';
		if ( 'logs_cleared' === $flash ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Logs cleared.', '4lfeedback' ) . '</p></div>';
		}

		$level_filter = isset( $_GET['level'] ) ? sanitize_key( wp_unslash( $_GET['level'] ) ) : '';
		$event_filter = isset( $_GET['event'] ) ? sanitize_key( wp_unslash( $_GET['event'] ) ) : '';
		$paged        = isset( $_GET['paged'] ) ? max( 1, (int) $_GET['paged'] ) : 1;
		$per_page     = 50;

		$rows = FourL_Feedback_Logger::get(
			array(
				'level'  => $level_filter,
				'event'  => $event_filter,
				'limit'  => $per_page,
				'offset' => ( $paged - 1 ) * $per_page,
			)
		);
		$total = FourL_Feedback_Logger::count(
			array(
				'level' => $level_filter,
				'event' => $event_filter,
			)
		);
		$pages = max( 1, (int) ceil( $total / $per_page ) );

		$base_url   = admin_url( 'admin.php?page=' . self::PAGE_SLUG . '-logs' );
		$is_enabled = FourL_Feedback_Logger::is_enabled();
		?>
		<div class="wrap">
			<h1 class="wp-heading-inline"><?php esc_html_e( '4L Feedback Logs', '4lfeedback' ); ?></h1>
			<form method="post" action="<?php echo esc_url( $base_url ); ?>" style="display: inline-block; margin-left: 10px;" onsubmit="return confirm('<?php echo esc_js( __( 'Clear all log entries?', '4lfeedback' ) ); ?>');">
				<?php wp_nonce_field( 'fourl_clear_logs' ); ?>
				<input type="hidden" name="fourl_action" value="clear_logs">
				<button type="submit" class="page-title-action"><?php esc_html_e( 'Clear logs', '4lfeedback' ); ?></button>
			</form>
			<hr class="wp-header-end">

			<p>
				<?php if ( $is_enabled ) : ?>
					<span style="color: #0F6E56;"><strong><?php esc_html_e( 'Verbose logging is ON', '4lfeedback' ); ?></strong></span> —
					<?php esc_html_e( 'every submission attempt is being recorded (info + debug events).', '4lfeedback' ); ?>
				<?php else : ?>
					<span style="color: #854F0B;"><strong><?php esc_html_e( 'Verbose logging is OFF', '4lfeedback' ); ?></strong></span> —
					<?php esc_html_e( 'only warnings and errors are recorded. Enable verbose logging in Settings to also capture successful submissions and validation events.', '4lfeedback' ); ?>
				<?php endif; ?>
			</p>

			<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>" style="margin: 12px 0;">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::PAGE_SLUG . '-logs' ); ?>">
				<label><?php esc_html_e( 'Level', '4lfeedback' ); ?>
					<select name="level">
						<option value=""><?php esc_html_e( 'All', '4lfeedback' ); ?></option>
						<?php foreach ( array( 'debug', 'info', 'warning', 'error' ) as $lvl ) : ?>
							<option value="<?php echo esc_attr( $lvl ); ?>" <?php selected( $level_filter, $lvl ); ?>><?php echo esc_html( ucfirst( $lvl ) ); ?></option>
						<?php endforeach; ?>
					</select>
				</label>
				<label><?php esc_html_e( 'Event', '4lfeedback' ); ?>
					<input type="text" name="event" value="<?php echo esc_attr( $event_filter ); ?>" placeholder="e.g. submission_saved">
				</label>
				<button type="submit" class="button"><?php esc_html_e( 'Filter', '4lfeedback' ); ?></button>
				<?php if ( $level_filter || $event_filter ) : ?>
					<a href="<?php echo esc_url( $base_url ); ?>" class="button"><?php esc_html_e( 'Reset', '4lfeedback' ); ?></a>
				<?php endif; ?>
			</form>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th style="width: 140px;"><?php esc_html_e( 'When', '4lfeedback' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Level', '4lfeedback' ); ?></th>
						<th style="width: 180px;"><?php esc_html_e( 'Event', '4lfeedback' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'Sub. ID', '4lfeedback' ); ?></th>
						<th style="width: 80px;"><?php esc_html_e( 'User', '4lfeedback' ); ?></th>
						<th><?php esc_html_e( 'Context', '4lfeedback' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr><td colspan="6"><?php esc_html_e( 'No log entries.', '4lfeedback' ); ?></td></tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$level_colors = array(
								'debug'   => '#888',
								'info'    => '#0F6E56',
								'warning' => '#854F0B',
								'error'   => '#A32D2D',
							);
							$color   = $level_colors[ $row['level'] ] ?? '#000';
							$user    = $row['user_id'] ? get_user_by( 'id', (int) $row['user_id'] ) : null;
							$user_lbl= $user ? $user->user_login : ( $row['user_id'] ? '#' . (int) $row['user_id'] : '—' );
							?>
							<tr>
								<td><?php echo esc_html( mysql2date( 'Y-m-d H:i:s', $row['created_at'] ) ); ?></td>
								<td><strong style="color: <?php echo esc_attr( $color ); ?>;"><?php echo esc_html( strtoupper( $row['level'] ) ); ?></strong></td>
								<td><code><?php echo esc_html( $row['event'] ); ?></code></td>
								<td>
									<?php if ( $row['submission_id'] ) : ?>
										<a href="<?php echo esc_url( admin_url( 'admin.php?page=' . self::PAGE_SLUG . '&view=' . (int) $row['submission_id'] ) ); ?>">#<?php echo (int) $row['submission_id']; ?></a>
									<?php else : ?>—<?php endif; ?>
								</td>
								<td><?php echo esc_html( $user_lbl ); ?></td>
								<td><pre style="white-space: pre-wrap; word-break: break-word; margin: 0; font-size: 11px; max-height: 200px; overflow: auto;"><?php echo esc_html( wp_json_encode( $row['context'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) ); ?></pre></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
				</tbody>
			</table>

			<?php if ( $pages > 1 ) : ?>
				<div class="tablenav"><div class="tablenav-pages">
					<?php
					$qs = array();
					if ( $level_filter ) { $qs['level'] = $level_filter; }
					if ( $event_filter ) { $qs['event'] = $event_filter; }
					echo wp_kses_post(
						paginate_links(
							array(
								'base'      => add_query_arg( array_merge( $qs, array( 'paged' => '%#%' ) ), $base_url ),
								'format'    => '',
								'prev_text' => '&laquo;',
								'next_text' => '&raquo;',
								'total'     => $pages,
								'current'   => $paged,
							)
						)
					);
					?>
				</div></div>
			<?php endif; ?>

			<h2 style="margin-top: 30px;"><?php esc_html_e( 'What the events mean', '4lfeedback' ); ?></h2>
			<table class="widefat striped" style="max-width: 900px;">
				<thead><tr><th><?php esc_html_e( 'Event', '4lfeedback' ); ?></th><th><?php esc_html_e( 'Meaning', '4lfeedback' ); ?></th></tr></thead>
				<tbody>
					<tr><td><code>submit_started</code></td><td><?php esc_html_e( 'AJAX handler entered (debug only).', '4lfeedback' ); ?></td></tr>
					<tr><td><code>nonce_failed</code></td><td><?php esc_html_e( 'Nonce mismatch. Likely cause: page cache served a stale nonce, or the session expired before the user clicked Submit.', '4lfeedback' ); ?></td></tr>
					<tr><td><code>validation_failed</code></td><td><?php esc_html_e( 'Server-side validation rejected the input (empty items, missing name/email).', '4lfeedback' ); ?></td></tr>
					<tr><td><code>submitted_logged_out</code></td><td><?php esc_html_e( 'Submission saved but the visitor was not logged in, so it has user_id=0 and will NOT appear under that user\'s breadcrumbs.', '4lfeedback' ); ?></td></tr>
					<tr><td><code>db_insert_failed</code></td><td><?php esc_html_e( 'Database refused the insert. Context includes the wpdb error.', '4lfeedback' ); ?></td></tr>
					<tr><td><code>submission_saved</code></td><td><?php esc_html_e( 'Submission saved successfully.', '4lfeedback' ); ?></td></tr>
					<tr><td><code>notification_sent / notification_failed</code></td><td><?php esc_html_e( 'wp_mail() result for the admin notification email.', '4lfeedback' ); ?></td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
