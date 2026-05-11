<?php
/**
 * Front-end shortcodes:
 *   [fourl_feedback_form]   - the 4Ls submission form
 *   [fourl_feedback_responses] - public admin responses
 *   [fourl_feedback_breadcrumbs] - submitted feedback "breadcrumbs"
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_Shortcodes {

	public function __construct() {
		add_shortcode( 'fourl_feedback_form', array( $this, 'render_form' ) );
		add_shortcode( 'fourl_feedback_responses', array( $this, 'render_responses' ) );
		add_shortcode( 'fourl_feedback_breadcrumbs', array( $this, 'render_breadcrumbs' ) );
	}

	public static function quadrants() {
		return array(
			'loved'   => array(
				'label'       => __( 'Loved', '4lfeedback' ),
				'subtitle'    => __( 'What worked. Keep doing it.', '4lfeedback' ),
				'placeholder' => __( 'Add what you loved…', '4lfeedback' ),
			),
			'loathed' => array(
				'label'       => __( 'Loathed', '4lfeedback' ),
				'subtitle'    => __( 'What dragged us down. Fix it.', '4lfeedback' ),
				'placeholder' => __( 'Add what you loathed…', '4lfeedback' ),
			),
			'longed'  => array(
				'label'       => __( 'Longed for', '4lfeedback' ),
				'subtitle'    => __( 'What was missing. Go get it.', '4lfeedback' ),
				'placeholder' => __( 'Add what you longed for…', '4lfeedback' ),
			),
			'learned' => array(
				'label'       => __( 'Learned', '4lfeedback' ),
				'subtitle'    => __( 'What we now know. Systemize it.', '4lfeedback' ),
				'placeholder' => __( 'Add what you learned…', '4lfeedback' ),
			),
		);
	}

	public function render_form( $atts ) {
		$atts = shortcode_atts(
			array(
				'title_label'      => __( 'DCU Course, Project, Event', '4lfeedback' ),
				'title_placeholder'=> __( 'e.g. Graniflex Course', '4lfeedback' ),
				'feedback_heading' => __( 'Feedback', '4lfeedback' ),
				'submit_label'     => __( 'Submit feedback', '4lfeedback' ),
				'show_name'        => 'yes',
				'show_email'       => 'yes',
				'show_title'       => 'yes',
				'show_breadcrumbs' => 'no',
				'breadcrumbs_limit'=> 10,
			),
			$atts,
			'fourl_feedback_form'
		);

		wp_enqueue_style( 'fourl-feedback' );
		wp_enqueue_script( 'fourl-feedback' );

		$settings        = FourL_Feedback_Plugin::get_settings();
		$require_email   = ! empty( $settings['require_email'] );
		$allow_anonymous = ! empty( $settings['allow_anonymous'] );
		$quadrants       = self::quadrants();

		// Stable form ID — HubSpot's non-HubSpot form capture fingerprints by
		// the id attribute, so a random per-request value causes it to register
		// a brand-new form on every page load. The static counter keeps each
		// instance on a page unique while staying deterministic across loads.
		static $instance_count = 0;
		$instance_count++;
		$form_id = 'fourl-feedback-form' . ( $instance_count > 1 ? '-' . $instance_count : '' );

		ob_start();
		?>
		<div class="fourl-feedback-wrapper">
			<form
				class="fourl-feedback-form"
				id="<?php echo esc_attr( $form_id ); ?>"
				data-hs-do-not-collect="true"
				data-hs-ignore="true"
				autocomplete="off"
				novalidate
			>

				<?php if ( 'yes' === $atts['show_title'] ) : ?>
					<div class="fourl-row fourl-meta">
						<label for="<?php echo esc_attr( $form_id ); ?>-title" class="fourl-meta-label">
							<?php echo esc_html( $atts['title_label'] ); ?>
						</label>
						<input
							type="text"
							id="<?php echo esc_attr( $form_id ); ?>-title"
							class="fourl-title-input"
							name="title"
							placeholder="<?php echo esc_attr( $atts['title_placeholder'] ); ?>"
						>
					</div>
				<?php endif; ?>

				<?php if ( ! empty( $atts['feedback_heading'] ) ) : ?>
					<h3 class="fourl-feedback-heading"><?php echo esc_html( $atts['feedback_heading'] ); ?></h3>
				<?php endif; ?>

				<div class="fourl-quadrant-grid">
					<?php foreach ( $quadrants as $key => $q ) : ?>
						<div class="fourl-quad fourl-quad-<?php echo esc_attr( $key ); ?>" data-key="<?php echo esc_attr( $key ); ?>">
							<div class="fourl-quad-header">
								<h3 class="fourl-quad-title"><?php echo esc_html( $q['label'] ); ?></h3>
								<span class="fourl-quad-count" data-count>0</span>
							</div>
							<p class="fourl-quad-subtitle"><?php echo esc_html( $q['subtitle'] ); ?></p>
							<ul class="fourl-quad-items" data-items></ul>
							<div class="fourl-quad-add">
								<input
									type="text"
									class="fourl-quad-input"
									placeholder="<?php echo esc_attr( $q['placeholder'] ); ?>"
									data-add-input
								>
								<button type="button" class="fourl-quad-add-btn" data-add-btn aria-label="<?php esc_attr_e( 'Add item', '4lfeedback' ); ?>">+</button>
							</div>
						</div>
					<?php endforeach; ?>
				</div>

				<div class="fourl-meta-grid">
					<?php if ( 'yes' === $atts['show_name'] ) : ?>
						<div class="fourl-meta-field">
							<label for="<?php echo esc_attr( $form_id ); ?>-name"><?php esc_html_e( 'Your name', '4lfeedback' ); ?> <?php if ( ! $allow_anonymous ) : ?><span class="fourl-required">*</span><?php endif; ?></label>
							<input type="text" id="<?php echo esc_attr( $form_id ); ?>-name" name="submitter_name"<?php if ( ! $allow_anonymous ) : ?> required<?php endif; ?>>
						</div>
					<?php endif; ?>
					<?php if ( 'yes' === $atts['show_email'] ) : ?>
						<div class="fourl-meta-field">
							<label for="<?php echo esc_attr( $form_id ); ?>-email"><?php esc_html_e( 'Your email', '4lfeedback' ); ?> <?php if ( $require_email ) : ?><span class="fourl-required">*</span><?php endif; ?></label>
							<input type="email" id="<?php echo esc_attr( $form_id ); ?>-email" name="submitter_email"<?php if ( $require_email ) : ?> required<?php endif; ?>>
						</div>
					<?php endif; ?>
				</div>

				<input type="hidden" name="action" value="fourl_feedback_submit">
				<input type="hidden" name="nonce" value="<?php echo esc_attr( wp_create_nonce( 'fourl_feedback_submit' ) ); ?>">
				<input type="text" name="fourl_hp" value="" tabindex="-1" autocomplete="off" class="fourl-hp" aria-hidden="true">

				<div class="fourl-actions">
					<button type="submit" class="fourl-submit-btn"><?php echo esc_html( $atts['submit_label'] ); ?></button>
					<span class="fourl-feedback-message" data-message role="status" aria-live="polite"></span>
				</div>
			</form>

			<?php if ( 'yes' === $atts['show_breadcrumbs'] ) : ?>
				<div class="fourl-inline-breadcrumbs">
					<h3 class="fourl-feedback-heading"><?php esc_html_e( 'Your previous feedback', '4lfeedback' ); ?></h3>
					<?php
					echo $this->render_breadcrumbs( // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
						array(
							'limit'      => (int) $atts['breadcrumbs_limit'],
							'show_items' => 'yes',
							'show_name'  => 'no',
						)
					);
					?>
				</div>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_responses( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'    => 10,
				'show_title' => 'yes',
			),
			$atts,
			'fourl_feedback_responses'
		);

		wp_enqueue_style( 'fourl-feedback' );

		ob_start();

		if ( ! is_user_logged_in() ) {
			?>
			<div class="fourl-responses-wrapper">
				<p class="fourl-empty"><?php esc_html_e( 'Please log in to view your responses.', '4lfeedback' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id   = get_current_user_id();
		$responses = FourL_Feedback_DB::get_responses_for_user( $user_id, max( 1, (int) $atts['limit'] ), true );
		?>
		<div class="fourl-responses-wrapper">
			<?php if ( empty( $responses ) ) : ?>
				<p class="fourl-empty"><?php esc_html_e( 'No responses yet.', '4lfeedback' ); ?></p>
			<?php else : ?>
				<ul class="fourl-responses-list">
					<?php foreach ( $responses as $r ) : ?>
						<li class="fourl-response">
							<?php if ( 'yes' === $atts['show_title'] && ! empty( $r['submission_title'] ) ) : ?>
								<div class="fourl-response-title"><?php echo esc_html( $r['submission_title'] ); ?></div>
							<?php endif; ?>
							<div class="fourl-response-body"><?php echo wp_kses_post( wpautop( $r['response_body'] ) ); ?></div>
							<div class="fourl-response-meta">
								<time datetime="<?php echo esc_attr( mysql2date( 'c', $r['created_at'] ) ); ?>">
									<?php echo esc_html( mysql2date( get_option( 'date_format' ), $r['created_at'] ) ); ?>
								</time>
							</div>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}

	public function render_breadcrumbs( $atts ) {
		$atts = shortcode_atts(
			array(
				'limit'      => 20,
				'status'     => '',
				'show_items' => 'yes',
				'show_name'  => 'yes',
			),
			$atts,
			'fourl_feedback_breadcrumbs'
		);

		wp_enqueue_style( 'fourl-feedback' );

		ob_start();

		if ( ! is_user_logged_in() ) {
			?>
			<div class="fourl-breadcrumbs-wrapper">
				<p class="fourl-empty"><?php esc_html_e( 'Please log in to view your feedback.', '4lfeedback' ); ?></p>
			</div>
			<?php
			return ob_get_clean();
		}

		$user_id = get_current_user_id();

		$rows = FourL_Feedback_DB::get_submissions(
			array(
				'limit'   => max( 1, (int) $atts['limit'] ),
				'status'  => sanitize_key( $atts['status'] ),
				'user_id' => $user_id,
			)
		);

		$quadrants = self::quadrants();
		?>
		<div class="fourl-breadcrumbs-wrapper">
			<?php if ( empty( $rows ) ) : ?>
				<p class="fourl-empty"><?php esc_html_e( 'No feedback submitted yet.', '4lfeedback' ); ?></p>
			<?php else : ?>
				<ul class="fourl-breadcrumbs-list">
					<?php foreach ( $rows as $row ) : ?>
						<li class="fourl-breadcrumb">
							<div class="fourl-breadcrumb-head">
								<?php if ( ! empty( $row['title'] ) ) : ?>
									<strong class="fourl-breadcrumb-title"><?php echo esc_html( $row['title'] ); ?></strong>
								<?php endif; ?>
								<?php if ( 'yes' === $atts['show_name'] && ! empty( $row['submitter_name'] ) ) : ?>
									<span class="fourl-breadcrumb-name">— <?php echo esc_html( $row['submitter_name'] ); ?></span>
								<?php endif; ?>
								<time class="fourl-breadcrumb-date" datetime="<?php echo esc_attr( mysql2date( 'c', $row['created_at'] ) ); ?>">
									<?php echo esc_html( mysql2date( get_option( 'date_format' ), $row['created_at'] ) ); ?>
								</time>
							</div>

							<?php if ( 'yes' === $atts['show_items'] ) : ?>
								<div class="fourl-breadcrumb-quadrants">
									<?php foreach ( $quadrants as $qkey => $q ) : ?>
										<?php
										$items = isset( $row['items'][ $qkey ] ) && is_array( $row['items'][ $qkey ] ) ? $row['items'][ $qkey ] : array();
										if ( empty( $items ) ) {
											continue;
										}
										?>
										<div class="fourl-breadcrumb-quad fourl-quad-<?php echo esc_attr( $qkey ); ?>">
											<div class="fourl-breadcrumb-quad-label"><?php echo esc_html( $q['label'] ); ?></div>
											<ul>
												<?php foreach ( $items as $item ) : ?>
													<?php
													$text    = is_array( $item ) && isset( $item['text'] ) ? $item['text'] : (string) $item;
													$starred = is_array( $item ) && ! empty( $item['starred'] );
													?>
													<li<?php echo $starred ? ' class="fourl-starred"' : ''; ?>>
														<?php if ( $starred ) : ?><span class="fourl-star" aria-hidden="true">★</span><?php endif; ?>
														<?php echo esc_html( $text ); ?>
													</li>
												<?php endforeach; ?>
											</ul>
										</div>
									<?php endforeach; ?>
								</div>
							<?php endif; ?>

						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>
		</div>
		<?php
		return ob_get_clean();
	}
}
