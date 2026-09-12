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
	const DB_VERSION = '1.0.0';
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
		flush_rewrite_rules();
	}

	public static function deactivate() {
		flush_rewrite_rules();
	}

	public function maybe_upgrade() {
		if ( get_option( 'tttc_db_version' ) !== self::DB_VERSION ) {
			self::create_relationship_table();
		}
	}

	public static function table_name() {
		global $wpdb;

		return $wpdb->prefix . 'tttc_tournament_players';
	}

	private static function create_relationship_table() {
		global $wpdb;

		$table_name      = self::table_name();
		$charset_collate = $wpdb->get_charset_collate();
		$sql             = "CREATE TABLE {$table_name} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			tournament_id bigint(20) unsigned NOT NULL,
			player_id bigint(20) unsigned NOT NULL,
			created_at datetime NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY tournament_player (tournament_id, player_id),
			KEY tournament_id (tournament_id),
			KEY player_id (player_id)
		) {$charset_collate};";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
		update_option( 'tttc_db_version', self::DB_VERSION );
	}

	public function delete_assignments( $post_id ) {
		$post_type = get_post_type( $post_id );

		if ( self::PLAYER_POST_TYPE !== $post_type && self::TOURNAMENT_POST_TYPE !== $post_type ) {
			return;
		}

		global $wpdb;
		$column = self::PLAYER_POST_TYPE === $post_type ? 'player_id' : 'tournament_id';
		$wpdb->delete( self::table_name(), array( $column => absint( $post_id ) ), array( '%d' ) );
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
}
