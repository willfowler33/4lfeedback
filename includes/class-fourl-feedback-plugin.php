<?php
/**
 * Main plugin bootstrap class.
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_Plugin {

	const OPTION_SETTINGS = 'fourl_feedback_settings';

	private static $instance = null;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		load_plugin_textdomain( '4lfeedback', false, dirname( FOURL_FEEDBACK_BASENAME ) . '/languages' );

		FourL_Feedback_DB::maybe_upgrade();

		new FourL_Feedback_Shortcodes();
		new FourL_Feedback_Ajax();

		if ( is_admin() ) {
			new FourL_Feedback_Admin();
		}

		add_action( 'wp_enqueue_scripts', array( $this, 'register_assets' ) );
	}

	public function register_assets() {
		wp_register_style(
			'fourl-feedback',
			FOURL_FEEDBACK_URL . 'assets/css/4lfeedback.css',
			array(),
			FOURL_FEEDBACK_VERSION
		);

		wp_register_script(
			'fourl-feedback',
			FOURL_FEEDBACK_URL . 'assets/js/4lfeedback.js',
			array(),
			FOURL_FEEDBACK_VERSION,
			true
		);

		wp_localize_script(
			'fourl-feedback',
			'FourLFeedback',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'nonce'   => wp_create_nonce( 'fourl_feedback_submit' ),
				'i18n'    => array(
					'thanks'    => __( 'Thanks — your feedback was submitted.', '4lfeedback' ),
					'error'     => __( 'Sorry, something went wrong. Please try again.', '4lfeedback' ),
					'empty'     => __( 'Please add at least one item before submitting.', '4lfeedback' ),
					'submitting'=> __( 'Submitting…', '4lfeedback' ),
				),
			)
		);
	}

	public static function get_settings() {
		$defaults = array(
			'notification_email' => get_option( 'admin_email' ),
			'require_email'      => 0,
			'allow_anonymous'    => 1,
		);
		$saved = get_option( self::OPTION_SETTINGS, array() );
		return wp_parse_args( $saved, $defaults );
	}

	public static function get_setting( $key ) {
		$settings = self::get_settings();
		return isset( $settings[ $key ] ) ? $settings[ $key ] : null;
	}
}
