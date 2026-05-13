<?php
/**
 * Lightweight logger backed by a custom DB table.
 *
 * Levels (lowest → highest severity): debug, info, warning, error.
 * Debug entries are skipped unless logging is enabled in settings.
 * Warning/error always log so we never miss a real failure.
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_Logger {

	const LEVEL_DEBUG   = 'debug';
	const LEVEL_INFO    = 'info';
	const LEVEL_WARNING = 'warning';
	const LEVEL_ERROR   = 'error';

	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'fourl_log';
	}

	public static function is_enabled() {
		$settings = FourL_Feedback_Plugin::get_settings();
		return ! empty( $settings['enable_logging'] );
	}

	/**
	 * @param string $level   One of LEVEL_* constants.
	 * @param string $event   Short event slug (snake_case).
	 * @param array  $context Arbitrary structured context (will be JSON-encoded).
	 * @param int    $submission_id Optional related submission id.
	 */
	public static function log( $level, $event, $context = array(), $submission_id = 0 ) {
		$always_log_levels = array( self::LEVEL_WARNING, self::LEVEL_ERROR );
		if ( ! in_array( $level, $always_log_levels, true ) && ! self::is_enabled() ) {
			return;
		}

		global $wpdb;
		$wpdb->insert(
			self::table(),
			array(
				'created_at'    => current_time( 'mysql' ),
				'level'         => $level,
				'event'         => substr( (string) $event, 0, 100 ),
				'submission_id' => (int) $submission_id,
				'user_id'       => (int) get_current_user_id(),
				'ip_address'    => isset( $_SERVER['REMOTE_ADDR'] ) ? substr( sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) ), 0, 45 ) : '',
				'context'       => wp_json_encode( $context ),
			),
			array( '%s', '%s', '%s', '%d', '%d', '%s', '%s' )
		);

		// Mirror serious problems into the standard PHP error log so they can also be
		// caught by WP_DEBUG_LOG / hosting log aggregators.
		if ( in_array( $level, $always_log_levels, true ) ) {
			error_log( sprintf( '[4lfeedback][%s] %s %s', $level, $event, wp_json_encode( $context ) ) );
		}
	}

	public static function debug( $event, $context = array(), $submission_id = 0 ) {
		self::log( self::LEVEL_DEBUG, $event, $context, $submission_id );
	}
	public static function info( $event, $context = array(), $submission_id = 0 ) {
		self::log( self::LEVEL_INFO, $event, $context, $submission_id );
	}
	public static function warning( $event, $context = array(), $submission_id = 0 ) {
		self::log( self::LEVEL_WARNING, $event, $context, $submission_id );
	}
	public static function error( $event, $context = array(), $submission_id = 0 ) {
		self::log( self::LEVEL_ERROR, $event, $context, $submission_id );
	}

	public static function get( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'level'  => '',
			'event'  => '',
			'limit'  => 100,
			'offset' => 0,
		);
		$args     = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['level'] ) ) {
			$where   .= ' AND level = %s';
			$params[] = $args['level'];
		}
		if ( ! empty( $args['event'] ) ) {
			$where   .= ' AND event = %s';
			$params[] = $args['event'];
		}

		$sql      = 'SELECT * FROM ' . self::table() . " WHERE {$where} ORDER BY id DESC LIMIT %d OFFSET %d";
		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore
		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['context'] = json_decode( $row['context'], true );
		}
		return $rows;
	}

	public static function count( $args = array() ) {
		global $wpdb;
		$defaults = array( 'level' => '', 'event' => '' );
		$args     = wp_parse_args( $args, $defaults );

		$where  = '1=1';
		$params = array();
		if ( ! empty( $args['level'] ) ) {
			$where   .= ' AND level = %s';
			$params[] = $args['level'];
		}
		if ( ! empty( $args['event'] ) ) {
			$where   .= ' AND event = %s';
			$params[] = $args['event'];
		}

		$sql = 'SELECT COUNT(*) FROM ' . self::table() . " WHERE {$where}";
		if ( $params ) {
			return (int) $wpdb->get_var( $wpdb->prepare( $sql, $params ) ); // phpcs:ignore
		}
		return (int) $wpdb->get_var( $sql ); // phpcs:ignore
	}

	public static function clear() {
		global $wpdb;
		$wpdb->query( 'TRUNCATE TABLE ' . self::table() ); // phpcs:ignore
	}

	public static function prune( $days = 30 ) {
		global $wpdb;
		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . self::table() . ' WHERE created_at < DATE_SUB(NOW(), INTERVAL %d DAY)', // phpcs:ignore
				(int) $days
			)
		);
	}
}
