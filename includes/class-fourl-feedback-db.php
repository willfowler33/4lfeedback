<?php
/**
 * Database layer: schema install/uninstall and CRUD helpers
 * for submissions and admin responses.
 *
 * @package FourLFeedback
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class FourL_Feedback_DB {

	const VERSION_OPTION = 'fourl_feedback_db_version';
	const DB_VERSION     = '1.0.0';

	public static function submissions_table() {
		global $wpdb;
		return $wpdb->prefix . 'fourl_submissions';
	}

	public static function responses_table() {
		global $wpdb;
		return $wpdb->prefix . 'fourl_responses';
	}

	public static function install() {
		global $wpdb;
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $wpdb->get_charset_collate();
		$submissions     = self::submissions_table();
		$responses       = self::responses_table();

		$sql_submissions = "CREATE TABLE {$submissions} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			title VARCHAR(255) NOT NULL DEFAULT '',
			submitter_name VARCHAR(190) NOT NULL DEFAULT '',
			submitter_email VARCHAR(190) NOT NULL DEFAULT '',
			items LONGTEXT NOT NULL,
			status VARCHAR(20) NOT NULL DEFAULT 'new',
			ip_address VARCHAR(45) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY status (status),
			KEY created_at (created_at)
		) {$charset_collate};";

		$sql_responses = "CREATE TABLE {$responses} (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			submission_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			response_body LONGTEXT NOT NULL,
			author_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			is_public TINYINT(1) NOT NULL DEFAULT 1,
			created_at DATETIME NOT NULL DEFAULT '0000-00-00 00:00:00',
			PRIMARY KEY  (id),
			KEY submission_id (submission_id),
			KEY is_public (is_public)
		) {$charset_collate};";

		dbDelta( $sql_submissions );
		dbDelta( $sql_responses );

		update_option( self::VERSION_OPTION, self::DB_VERSION );
	}

	public static function uninstall() {
		global $wpdb;
		$submissions = self::submissions_table();
		$responses   = self::responses_table();
		$wpdb->query( "DROP TABLE IF EXISTS {$responses}" ); // phpcs:ignore
		$wpdb->query( "DROP TABLE IF EXISTS {$submissions}" ); // phpcs:ignore
		delete_option( self::VERSION_OPTION );
		delete_option( FourL_Feedback_Plugin::OPTION_SETTINGS );
	}

	public static function insert_submission( $data ) {
		global $wpdb;

		$row = array(
			'title'           => isset( $data['title'] ) ? wp_strip_all_tags( $data['title'] ) : '',
			'submitter_name'  => isset( $data['submitter_name'] ) ? wp_strip_all_tags( $data['submitter_name'] ) : '',
			'submitter_email' => isset( $data['submitter_email'] ) ? sanitize_email( $data['submitter_email'] ) : '',
			'items'           => wp_json_encode( isset( $data['items'] ) ? $data['items'] : array() ),
			'status'          => 'new',
			'ip_address'      => isset( $data['ip_address'] ) ? substr( $data['ip_address'], 0, 45 ) : '',
			'created_at'      => current_time( 'mysql' ),
		);

		$ok = $wpdb->insert(
			self::submissions_table(),
			$row,
			array( '%s', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $ok ) {
			return new WP_Error( 'fourl_feedback_db_insert_failed', __( 'Could not save submission.', '4lfeedback' ) );
		}

		return (int) $wpdb->insert_id;
	}

	public static function get_submission( $id ) {
		global $wpdb;
		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . self::submissions_table() . ' WHERE id = %d', $id ), // phpcs:ignore
			ARRAY_A
		);
		if ( ! $row ) {
			return null;
		}
		$row['items'] = json_decode( $row['items'], true );
		if ( ! is_array( $row['items'] ) ) {
			$row['items'] = array();
		}
		return $row;
	}

	public static function get_submissions( $args = array() ) {
		global $wpdb;
		$defaults = array(
			'status'   => '',
			'limit'    => 20,
			'offset'   => 0,
			'orderby'  => 'created_at',
			'order'    => 'DESC',
		);
		$args     = wp_parse_args( $args, $defaults );

		$allowed_orderby = array( 'id', 'created_at', 'title', 'status' );
		$orderby = in_array( $args['orderby'], $allowed_orderby, true ) ? $args['orderby'] : 'created_at';
		$order   = strtoupper( $args['order'] ) === 'ASC' ? 'ASC' : 'DESC';

		$where  = '1=1';
		$params = array();

		if ( ! empty( $args['status'] ) ) {
			$where   .= ' AND status = %s';
			$params[] = $args['status'];
		}

		$sql = 'SELECT * FROM ' . self::submissions_table() . " WHERE {$where} ORDER BY {$orderby} {$order} LIMIT %d OFFSET %d";

		$params[] = (int) $args['limit'];
		$params[] = (int) $args['offset'];

		$rows = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A ); // phpcs:ignore

		if ( ! $rows ) {
			return array();
		}

		foreach ( $rows as &$row ) {
			$row['items'] = json_decode( $row['items'], true );
			if ( ! is_array( $row['items'] ) ) {
				$row['items'] = array();
			}
		}

		return $rows;
	}

	public static function count_submissions( $status = '' ) {
		global $wpdb;
		if ( $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::submissions_table() . ' WHERE status = %s', $status ) // phpcs:ignore
			);
		}
		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . self::submissions_table() ); // phpcs:ignore
	}

	public static function update_submission_status( $id, $status ) {
		global $wpdb;
		$allowed = array( 'new', 'reviewed', 'actioned', 'archived' );
		if ( ! in_array( $status, $allowed, true ) ) {
			return false;
		}
		return false !== $wpdb->update(
			self::submissions_table(),
			array( 'status' => $status ),
			array( 'id' => (int) $id ),
			array( '%s' ),
			array( '%d' )
		);
	}

	public static function delete_submission( $id ) {
		global $wpdb;
		$id = (int) $id;
		$wpdb->delete( self::responses_table(), array( 'submission_id' => $id ), array( '%d' ) );
		return false !== $wpdb->delete( self::submissions_table(), array( 'id' => $id ), array( '%d' ) );
	}

	public static function insert_response( $submission_id, $body, $author_id = 0, $is_public = 1 ) {
		global $wpdb;
		$ok = $wpdb->insert(
			self::responses_table(),
			array(
				'submission_id' => (int) $submission_id,
				'response_body' => wp_kses_post( $body ),
				'author_id'     => (int) $author_id,
				'is_public'     => $is_public ? 1 : 0,
				'created_at'    => current_time( 'mysql' ),
			),
			array( '%d', '%s', '%d', '%d', '%s' )
		);
		if ( false === $ok ) {
			return new WP_Error( 'fourl_feedback_response_insert_failed', __( 'Could not save response.', '4lfeedback' ) );
		}
		return (int) $wpdb->insert_id;
	}

	public static function get_responses_for( $submission_id, $public_only = false ) {
		global $wpdb;
		if ( $public_only ) {
			$sql = $wpdb->prepare(
				'SELECT * FROM ' . self::responses_table() . ' WHERE submission_id = %d AND is_public = 1 ORDER BY created_at ASC', // phpcs:ignore
				(int) $submission_id
			);
		} else {
			$sql = $wpdb->prepare(
				'SELECT * FROM ' . self::responses_table() . ' WHERE submission_id = %d ORDER BY created_at ASC', // phpcs:ignore
				(int) $submission_id
			);
		}
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		return $rows ? $rows : array();
	}

	public static function get_recent_public_responses( $limit = 10 ) {
		global $wpdb;
		$sql = $wpdb->prepare(
			'SELECT r.*, s.title AS submission_title FROM ' . self::responses_table() . ' r ' . // phpcs:ignore
			'LEFT JOIN ' . self::submissions_table() . ' s ON s.id = r.submission_id ' . // phpcs:ignore
			'WHERE r.is_public = 1 ORDER BY r.created_at DESC LIMIT %d',
			(int) $limit
		);
		$rows = $wpdb->get_results( $sql, ARRAY_A ); // phpcs:ignore
		return $rows ? $rows : array();
	}

	public static function delete_response( $id ) {
		global $wpdb;
		return false !== $wpdb->delete( self::responses_table(), array( 'id' => (int) $id ), array( '%d' ) );
	}
}
