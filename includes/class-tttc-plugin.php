<?php
/**
 * Core plugin functionality.
 *
 * @package TableTennisTournamentForClubs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TTTC_Plugin {
	const DB_VERSION = '1.5.0';
	const PLAYER_POST_TYPE = 'tttc_player';
	const TOURNAMENT_POST_TYPE = 'tttc_tournament';
	const PLAYER_META_RATING = '_tttc_rating';
	const PLAYER_META_EMAIL = '_tttc_email';
	const PLAYER_META_ACTIVE = '_tttc_active';
	const PLAYER_META_GENDER = '_tttc_gender';
	const PLAYER_META_TYPE = '_tttc_type';
	const TOURNAMENT_META_DATE = '_tttc_date';
	const TOURNAMENT_META_GAMES = '_tttc_games';
	const TOURNAMENT_META_STATUS = '_tttc_status';
	const TOURNAMENT_META_TYPE = '_tttc_tournament_type';
	const TOURNAMENT_META_FORMAT_RANGES = '_tttc_format_ranges';
	const OPTION_EMAIL_FROM = 'tttc_email_from';
	const OPTION_EMAIL_SUBJECT = 'tttc_email_subject';
	const OPTION_EMAIL_BODY = 'tttc_email_body';
	const OPTION_DEFAULT_GAMES = 'tttc_default_games';
	const OPTION_DEFAULT_TYPE = 'tttc_default_type';
	const OPTION_FORMAT_RANGES = 'tttc_format_ranges';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'plugins_loaded', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'before_delete_post', array( $this, 'delete_assignments' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		new TTTC_Admin();
		new TTTC_Public();
	}

	public function load_textdomain() {
		load_plugin_textdomain(
			'table-tennis-tournament-for-clubs',
			false,
			dirname( plugin_basename( TTTC_FILE ) ) . '/languages'
		);
	}

	public static function activate() {
		self::instance()->register_post_types();
		self::create_relationship_table();
		TTTC_Public::register_rewrite();
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function maybe_upgrade() {
		if ( get_option( 'tttc_db_version' ) !== self::DB_VERSION ) {
			self::create_relationship_table();
			TTTC_Public::register_rewrite();
			flush_rewrite_rules();
		}
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'tttc_tournament_players';
	}

	public static function scores_table_name() {
		global $wpdb;

		return $wpdb->prefix . 'tttc_tournament_scores';
	}

	private static function create_relationship_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL,
			player_id bigint(20) unsigned NOT NULL,
			rating bigint(20) unsigned DEFAULT NULL,
			seed bigint(20) unsigned DEFAULT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_player (tournament_id, player_id),
			KEY tournament_id (tournament_id),
			KEY player_id (player_id)
		) {$charset_collate};";
		$scores_table = self::scores_table_name();
		$scores_sql   = "CREATE TABLE {$scores_table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL,
			match_key varchar(32) NOT NULL,
			player_one_id bigint(20) unsigned NOT NULL,
			player_two_id bigint(20) unsigned NOT NULL,
			round_number smallint(5) unsigned NOT NULL,
			match_number smallint(5) unsigned NOT NULL,
			scores longtext NOT NULL,
			updated_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_match (tournament_id, match_key),
			KEY tournament_id (tournament_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		dbDelta( $scores_sql );
		$wpdb->query(
			"UPDATE {$table_name} AS assignments
			INNER JOIN {$wpdb->postmeta} AS player_meta ON player_meta.post_id = assignments.player_id AND player_meta.meta_key = '" . self::PLAYER_META_RATING . "'
			SET assignments.rating = CAST( player_meta.meta_value AS UNSIGNED )
			WHERE assignments.rating IS NULL AND player_meta.meta_value != ''"
		);
		self::backfill_seed_order();
		update_option( 'tttc_db_version', self::DB_VERSION );
	}

	/**
	 * Gives every existing tournament assignment an initial seed order (rating desc, unrated last)
	 * so manual reordering has a stable starting point instead of leaving new rows at NULL.
	 */
	private static function backfill_seed_order() {
		global $wpdb;
		$table_name     = self::table_name();
		$tournament_ids = $wpdb->get_col( "SELECT DISTINCT tournament_id FROM {$table_name} WHERE seed IS NULL" );

		foreach ( $tournament_ids as $tournament_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare( "SELECT id, player_id, rating FROM {$table_name} WHERE tournament_id = %d", $tournament_id ) );
			usort(
				$rows,
				function( $first, $second ) {
					$rating_difference = ( null === $second->rating ? -1 : (int) $second->rating ) - ( null === $first->rating ? -1 : (int) $first->rating );
					if ( 0 !== $rating_difference ) {
						return $rating_difference;
					}
					$title_difference = strcasecmp( get_the_title( $first->player_id ), get_the_title( $second->player_id ) );
					return 0 !== $title_difference ? $title_difference : $first->player_id - $second->player_id;
				}
			);
			foreach ( $rows as $index => $row ) {
				$wpdb->update( $table_name, array( 'seed' => $index + 1 ), array( 'id' => $row->id ), array( '%d' ), array( '%d' ) );
			}
		}
	}

	public function delete_assignments( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( self::PLAYER_POST_TYPE !== $post_type && self::TOURNAMENT_POST_TYPE !== $post_type ) {
			return;
		}

		global $wpdb;
		$column = self::PLAYER_POST_TYPE === $post_type ? 'player_id' : 'tournament_id';
		$wpdb->delete( self::table_name(), array( $column => absint( $post_id ) ), array( '%d' ) );
		if ( self::TOURNAMENT_POST_TYPE === $post_type ) {
			$wpdb->delete( self::scores_table_name(), array( 'tournament_id' => absint( $post_id ) ), array( '%d' ) );
		}
	}

	public function register_post_types() {
		register_post_type(
			self::PLAYER_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Players', 'table-tennis-tournament-for-clubs' ),
					'singular_name' => __( 'Player', 'table-tennis-tournament-for-clubs' ),
					'add_new_item'  => __( 'Add Player', 'table-tennis-tournament-for-clubs' ),
					'edit_item'     => __( 'Edit Player', 'table-tennis-tournament-for-clubs' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-groups',
			)
		);

		register_post_type(
			self::TOURNAMENT_POST_TYPE,
			array(
				'labels'       => array(
					'name'          => __( 'Tournaments', 'table-tennis-tournament-for-clubs' ),
					'singular_name' => __( 'Tournament', 'table-tennis-tournament-for-clubs' ),
					'add_new_item'  => __( 'Add Tournament', 'table-tennis-tournament-for-clubs' ),
					'edit_item'     => __( 'Edit Tournament', 'table-tennis-tournament-for-clubs' ),
				),
				'public'       => false,
				'show_ui'      => true,
				'show_in_menu' => false,
				'supports'     => array( 'title' ),
				'menu_icon'    => 'dashicons-awards',
			)
		);
	}

	public static function statuses() {
		return array(
			'draft'     => __( 'Draft', 'table-tennis-tournament-for-clubs' ),
			'upcoming'  => __( 'Upcoming', 'table-tennis-tournament-for-clubs' ),
			'active'    => __( 'Active', 'table-tennis-tournament-for-clubs' ),
			'completed' => __( 'Completed', 'table-tennis-tournament-for-clubs' ),
			'cancelled' => __( 'Cancelled', 'table-tennis-tournament-for-clubs' ),
		);
	}

	public static function default_format_ranges() {
		return array(
			1 => array( 'min' => 4, 'max' => 7 ),
			2 => array( 'min' => 8, 'max' => 14 ),
			3 => array( 'min' => 15, 'max' => 19 ),
			4 => array( 'min' => 20, 'max' => 28 ),
		);
	}

	public static function sanitize_format_ranges( $input ) {
		$defaults = self::default_format_ranges();
		if ( ! is_array( $input ) ) {
			return $defaults;
		}

		$sanitized = array();
		for ( $groups = 1; $groups <= 4; $groups++ ) {
			$min = isset( $input[ $groups ]['min'] ) ? absint( $input[ $groups ]['min'] ) : $defaults[ $groups ]['min'];
			$max = isset( $input[ $groups ]['max'] ) ? absint( $input[ $groups ]['max'] ) : $defaults[ $groups ]['max'];

			if ( $min < 2 ) {
				$min = 2;
			}
			if ( $max < $min ) {
				$max = $min;
			}

			$sanitized[ $groups ] = array(
				'min' => $min,
				'max' => $max,
			);
		}

		return $sanitized;
	}

	public static function get_format_ranges( $tournament_id = 0 ) {
		if ( $tournament_id ) {
			$meta = get_post_meta( $tournament_id, self::TOURNAMENT_META_FORMAT_RANGES, true );
			if ( is_array( $meta ) && ! empty( $meta ) ) {
				return self::sanitize_format_ranges( $meta );
			}
		}

		$option = get_option( self::OPTION_FORMAT_RANGES );
		if ( is_array( $option ) && ! empty( $option ) ) {
			return self::sanitize_format_ranges( $option );
		}

		return self::default_format_ranges();
	}

	public static function has_scores( $tournament_id ) {
		if ( ! $tournament_id ) {
			return false;
		}

		global $wpdb;
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM ' . self::scores_table_name() . ' WHERE tournament_id = %d', $tournament_id ) );

		return (int) $count > 0;
	}
}
