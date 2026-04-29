<?php
/**
 * AJAX submission handler. Validates, persists, and emails.
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_Ajax {

	public function __construct() {
		add_action( 'wp_ajax_fourl_feedback_submit',        array( $this, 'handle_submit' ) );
		add_action( 'wp_ajax_nopriv_fourl_feedback_submit', array( $this, 'handle_submit' ) );
	}

	public function handle_submit() {
		check_ajax_referer( 'fourl_feedback_submit', 'nonce' );

		// Honeypot.
		if ( ! empty( $_POST['fourl_hp'] ) ) {
			wp_send_json_success( array( 'message' => __( 'Thanks — your feedback was submitted.', '4lfeedback' ) ) );
		}

		$settings = FourL_Feedback_Plugin::get_settings();

		$title           = isset( $_POST['title'] ) ? sanitize_text_field( wp_unslash( $_POST['title'] ) ) : '';
		$submitter_name  = isset( $_POST['submitter_name'] ) ? sanitize_text_field( wp_unslash( $_POST['submitter_name'] ) ) : '';
		$submitter_email = isset( $_POST['submitter_email'] ) ? sanitize_email( wp_unslash( $_POST['submitter_email'] ) ) : '';
		$raw_items       = isset( $_POST['items'] ) ? wp_unslash( $_POST['items'] ) : '';

		if ( empty( $settings['allow_anonymous'] ) && empty( $submitter_name ) ) {
			wp_send_json_error( array( 'message' => __( 'Please enter your name.', '4lfeedback' ) ) );
		}

		if ( ! empty( $settings['require_email'] ) && ! is_email( $submitter_email ) ) {
			wp_send_json_error( array( 'message' => __( 'A valid email is required.', '4lfeedback' ) ) );
		}

		$items = $this->sanitize_items( $raw_items );

		$total = 0;
		foreach ( $items as $list ) {
			$total += count( $list );
		}
		if ( 0 === $total ) {
			wp_send_json_error( array( 'message' => __( 'Please add at least one item before submitting.', '4lfeedback' ) ) );
		}

		$id = FourL_Feedback_DB::insert_submission(
			array(
				'title'           => $title,
				'submitter_name'  => $submitter_name,
				'submitter_email' => $submitter_email,
				'items'           => $items,
				'ip_address'      => $this->get_ip(),
			)
		);

		if ( is_wp_error( $id ) ) {
			wp_send_json_error( array( 'message' => $id->get_error_message() ) );
		}

		$this->send_notification( $id, $title, $submitter_name, $submitter_email, $items );

		wp_send_json_success(
			array(
				'message' => __( 'Thanks — your feedback was submitted.', '4lfeedback' ),
				'id'      => (int) $id,
			)
		);
	}

	private function sanitize_items( $raw ) {
		$out = array(
			'loved'   => array(),
			'loathed' => array(),
			'longed'  => array(),
			'learned' => array(),
		);

		if ( empty( $raw ) ) {
			return $out;
		}

		$decoded = json_decode( $raw, true );
		if ( ! is_array( $decoded ) ) {
			return $out;
		}

		foreach ( array_keys( $out ) as $key ) {
			if ( empty( $decoded[ $key ] ) || ! is_array( $decoded[ $key ] ) ) {
				continue;
			}
			foreach ( $decoded[ $key ] as $item ) {
				$text    = '';
				$starred = false;
				if ( is_array( $item ) ) {
					$text    = isset( $item['text'] ) ? sanitize_text_field( $item['text'] ) : '';
					$starred = ! empty( $item['starred'] );
				} else {
					$text = sanitize_text_field( (string) $item );
				}
				if ( '' === $text ) {
					continue;
				}
				$out[ $key ][] = array(
					'text'    => $text,
					'starred' => (bool) $starred,
				);
			}
		}

		return $out;
	}

	private function get_ip() {
		$ip = isset( $_SERVER['REMOTE_ADDR'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ) : '';
		return $ip;
	}

	private function send_notification( $id, $title, $name, $email, $items ) {
		$settings   = FourL_Feedback_Plugin::get_settings();
		$to         = ! empty( $settings['notification_email'] ) ? $settings['notification_email'] : get_option( 'admin_email' );
		$site_name  = wp_specialchars_decode( get_bloginfo( 'name' ), ENT_QUOTES );
		$subject    = sprintf(
			/* translators: 1: site name, 2: project title */
			__( '[%1$s] New 4L feedback: %2$s', '4lfeedback' ),
			$site_name,
			$title ? $title : __( 'Untitled', '4lfeedback' )
		);

		$labels = array(
			'loved'   => __( 'Loved', '4lfeedback' ),
			'loathed' => __( 'Loathed', '4lfeedback' ),
			'longed'  => __( 'Longed for', '4lfeedback' ),
			'learned' => __( 'Learned', '4lfeedback' ),
		);

		$lines   = array();
		$lines[] = sprintf( __( 'New 4L feedback submitted on %s', '4lfeedback' ), $site_name );
		$lines[] = str_repeat( '=', 50 );
		$lines[] = '';
		$lines[] = __( 'Project / sprint:', '4lfeedback' ) . ' ' . ( $title ? $title : __( '(none)', '4lfeedback' ) );
		$lines[] = __( 'Name:', '4lfeedback' ) . ' ' . ( $name ? $name : __( '(anonymous)', '4lfeedback' ) );
		$lines[] = __( 'Email:', '4lfeedback' ) . ' ' . ( $email ? $email : __( '(none)', '4lfeedback' ) );
		$lines[] = __( 'Submission ID:', '4lfeedback' ) . ' ' . $id;
		$lines[] = '';

		foreach ( $labels as $key => $label ) {
			$list = isset( $items[ $key ] ) ? $items[ $key ] : array();
			$lines[] = strtoupper( $label ) . ' (' . count( $list ) . ')';
			if ( empty( $list ) ) {
				$lines[] = '  (none)';
			} else {
				foreach ( $list as $item ) {
					$prefix  = ! empty( $item['starred'] ) ? '  ★ ' : '  - ';
					$lines[] = $prefix . $item['text'];
				}
			}
			$lines[] = '';
		}

		$lines[] = sprintf(
			__( 'Manage submission: %s', '4lfeedback' ),
			admin_url( 'admin.php?page=fourl-feedback&view=' . (int) $id )
		);

		$body    = implode( "\n", $lines );
		$headers = array();
		if ( $email ) {
			$from_name = $name ? $name : __( '4L Feedback', '4lfeedback' );
			$headers[] = 'Reply-To: ' . sprintf( '%s <%s>', $from_name, $email );
		}

		wp_mail( $to, $subject, $body, $headers );
	}
}
