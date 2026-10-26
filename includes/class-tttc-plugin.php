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
	const PLAYER_META_PHONE = '_tttc_phone';
	const PLAYER_META_ACTIVE = '_tttc_active';
	const PLAYER_META_GENDER = '_tttc_gender';
	const PLAYER_META_TYPE = '_tttc_type';
	const TOURNAMENT_META_DATE = '_tttc_date';
	const TOURNAMENT_META_TIME = '_tttc_time';
	const TOURNAMENT_META_GAMES = '_tttc_games';
		const TOURNAMENT_META_INTERMEDIATE_GAMES = '_tttc_intermediate_games';
		const TOURNAMENT_META_FINALS_GAMES = '_tttc_finals_games';
	const TOURNAMENT_META_STATUS = '_tttc_status';
	const TOURNAMENT_META_HIDE_FROM_OVERVIEW = '_tttc_hide_from_overview';
	const TOURNAMENT_META_TYPE = '_tttc_tournament_type';
	const TOURNAMENT_META_COMPETITION_FORMAT = '_tttc_competition_format';
	const TOURNAMENT_META_PLACEMENT_POOLS = '_tttc_placement_pools';
	const TOURNAMENT_META_FORMAT_RANGES = '_tttc_format_ranges';
	const TOURNAMENT_META_NO_FOLLOW_UP = '_tttc_no_follow_up';
	const TOURNAMENT_META_DESCRIPTION = '_tttc_description';
	const TOURNAMENT_META_PHONE_REQUIRED = '_tttc_phone_required';
	const TOURNAMENT_META_ASK_JOINING = '_tttc_ask_joining';
	const TOURNAMENT_META_NOT_JOINING_EMAIL = '_tttc_not_joining_email';
	const TOURNAMENT_META_TIKKIE_ENABLED = '_tttc_tikkie_enabled';
	const TOURNAMENT_META_TIKKIE_QR = '_tttc_tikkie_qr';
	const TOURNAMENT_META_TIKKIE_AMOUNT = '_tttc_tikkie_amount';
	const TOURNAMENT_META_TIKKIE_URL = '_tttc_tikkie_url';
	const OPTION_EMAIL_FROM = 'tttc_email_from';
	const OPTION_EMAIL_SUBJECT = 'tttc_email_subject';
	const OPTION_EMAIL_BODY = 'tttc_email_body';
	const OPTION_DEFAULT_GAMES = 'tttc_default_games';
	const OPTION_DEFAULT_TYPE = 'tttc_default_type';
	const OPTION_FORMAT_RANGES = 'tttc_format_ranges';
	const OPTION_TV_INTERVAL = 'tttc_tv_interval';
	const OPTION_TOURNAMENT_URL_BASE = 'tttc_tournament_url_base';
	const OPTION_BANK_ACCOUNT = 'tttc_bank_account';
	const DEFAULT_TOURNAMENT_URL_BASE = 'toernooi';
	const TRANSIENT_FLUSH_TOURNAMENT_REWRITES = 'tttc_flush_tournament_rewrites';

	private static $instance;

	public static function instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	private function __construct() {
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'before_delete_post', array( $this, 'delete_assignments' ) );
		add_action( 'admin_init', array( $this, 'maybe_upgrade' ) );
		add_action( 'added_option', array( $this, 'schedule_tournament_rewrite_flush' ), 10, 2 );
		add_action( 'updated_option', array( $this, 'schedule_tournament_rewrite_flush' ), 10, 3 );
		new TTTC_Admin();
		new TTTC_Public();
	}

	public static function sanitize_tournament_url_base( $value ) {
		$value = sanitize_title( (string) $value );

		if ( '' === $value || 'speler' === $value ) {
			return self::DEFAULT_TOURNAMENT_URL_BASE;
		}

		return $value;
	}

	public static function get_tournament_url_base() {
		return self::sanitize_tournament_url_base( get_option( self::OPTION_TOURNAMENT_URL_BASE, self::DEFAULT_TOURNAMENT_URL_BASE ) );
	}

	public static function get_tournament_game_formats( $tournament_id ) {
		$default_games = get_option( self::OPTION_DEFAULT_GAMES, '3' );
		if ( ! in_array( (string) $default_games, array( '1', '3', '5' ), true ) ) {
			$default_games = '3';
		}

		$preliminary = get_post_meta( $tournament_id, self::TOURNAMENT_META_GAMES, true );
		if ( ! in_array( (string) $preliminary, array( '1', '3', '5' ), true ) ) {
			$preliminary = $default_games;
		}
		$intermediate = get_post_meta( $tournament_id, self::TOURNAMENT_META_INTERMEDIATE_GAMES, true );
		if ( ! in_array( (string) $intermediate, array( '1', '3', '5' ), true ) ) {
			$intermediate = $preliminary;
		}
		$finals = get_post_meta( $tournament_id, self::TOURNAMENT_META_FINALS_GAMES, true );
		if ( ! in_array( (string) $finals, array( '1', '3', '5' ), true ) ) {
			$finals = $preliminary;
		}

		return array(
			'preliminary' => (int) $preliminary,
			'intermediate' => (int) $intermediate,
			'finals' => (int) $finals,
		);
	}

	public static function get_tournament_competition_format( $tournament_id ) {
		$format = get_post_meta( $tournament_id, self::TOURNAMENT_META_COMPETITION_FORMAT, true );

		return 'placement_pools' === $format ? $format : 'standard';
	}

	public static function placement_group_sizes( $player_count ) {
		$player_count = absint( $player_count );
		if ( $player_count < 9 ) {
			return array();
		}

		$remainder = $player_count % 3;
		$group_count = (int) floor( $player_count / 3 );
		$four_player_groups = 0;
		if ( 1 === $remainder ) {
			$four_player_groups = 1;
		} elseif ( 2 === $remainder ) {
			$four_player_groups = 2;
		}

		$sizes = array_fill( 0, $group_count, 3 );
		for ( $index = 0; $index < $four_player_groups; $index++ ) {
			$offset = (int) floor( ( $group_count - 1 ) / 2 ) + $index;
			$sizes[ $offset ] = 4;
		}

		return $sizes;
	}

	public static function placement_score_key( $phase, $group, $first_id, $second_id ) {
		$ids = array( absint( $first_id ), absint( $second_id ) );
		sort( $ids, SORT_NUMERIC );
		if ( 'preliminary' === $phase ) {
			return $ids[0] . '-' . $ids[1];
		}

		return 'p' . sanitize_key( $group ) . '-' . substr( md5( $ids[0] . '-' . $ids[1] ), 0, 16 );
	}

	public static function group_scores( $schedule, $scores, $phase, $group = '' ) {
		$group_scores = array();
		foreach ( $schedule as $group_key => $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round ) {
				foreach ( $round as $match ) {
					$score_group = '' === $group && 'placement' === $phase ? $group_key : $group;
					$score_key = self::placement_score_key( $phase, $score_group, $match[0]->ID, $match[1]->ID );
					$player_ids = array( absint( $match[0]->ID ), absint( $match[1]->ID ) );
					sort( $player_ids, SORT_NUMERIC );
					if ( isset( $scores[ $score_key ] ) ) {
						$group_scores[ $player_ids[0] . '-' . $player_ids[1] ] = $scores[ $score_key ];
					}
				}
			}
		}

		return $group_scores;
	}

	public function schedule_tournament_rewrite_flush( $option ) {
		if ( self::OPTION_TOURNAMENT_URL_BASE === $option ) {
			set_transient( self::TRANSIENT_FLUSH_TOURNAMENT_REWRITES, 1 );
		}
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
			$wpdb->prepare(
				'UPDATE %i AS assignments
				INNER JOIN %i AS player_meta ON player_meta.post_id = assignments.player_id AND player_meta.meta_key = %s
				SET assignments.rating = CAST( player_meta.meta_value AS UNSIGNED )
				WHERE assignments.rating IS NULL AND player_meta.meta_value != %s',
				$table_name,
				$wpdb->postmeta,
				self::PLAYER_META_RATING,
				''
			)
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
		$tournament_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT DISTINCT tournament_id FROM %i WHERE seed IS NULL', $table_name ) );

		foreach ( $tournament_ids as $tournament_id ) {
			$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT id, player_id, rating FROM %i WHERE tournament_id = %d', $table_name, $tournament_id ) );
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
		$count = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE tournament_id = %d', self::scores_table_name(), $tournament_id ) );

		return (int) $count > 0;
	}
}