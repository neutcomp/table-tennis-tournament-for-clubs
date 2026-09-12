<?php
/**
 * Public shortcodes.
 *
 * @package TableTennisTournamentForClubs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TTTC_Public {
	public function __construct() {
		add_shortcode( 'tttc_tournaments', array( $this, 'tournaments_shortcode' ) );
		add_shortcode( 'tttc_players', array( $this, 'players_shortcode' ) );
	}

	public function tournaments_shortcode( $atts ) {
		$atts = shortcode_atts( array( 'id' => 0, 'limit' => 10 ), $atts, 'tttc_tournaments' );
		$args = array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => max( 1, absint( $atts['limit'] ) ), 'orderby' => 'meta_value', 'meta_key' => TTTC_Plugin::TOURNAMENT_META_DATE, 'order' => 'ASC' );
		if ( absint( $atts['id'] ) ) {
			$args['p'] = absint( $atts['id'] );
		}
		$query = new WP_Query( $args );
		if ( ! $query->have_posts() ) {
			return '<p class="tttc-empty">' . esc_html__( 'No tournaments found.', 'table-tennis-tournament-for-clubs' ) . '</p>';
		}
		$output = '<div class="tttc-tournaments">';
		while ( $query->have_posts() ) {
			$query->the_post();
			$post_id = get_the_ID();
			$status  = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			$label   = isset( TTTC_Plugin::statuses()[ $status ] ) ? TTTC_Plugin::statuses()[ $status ] : $status;
			$output .= '<article class="tttc-tournament"><h3>' . esc_html( get_the_title() ) . '</h3><dl><dt>' . esc_html__( 'Date', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true ) ) . '</dd><dt>' . esc_html__( 'Best of', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true ) ) . '</dd><dt>' . esc_html__( 'Status', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( $label ) . '</dd></dl>' . $this->assigned_players_markup( $post_id ) . '</article>';
		}
		wp_reset_postdata();
		return $output . '</div>';
	}

	public function players_shortcode( $atts ) {
		$atts  = shortcode_atts( array( 'limit' => 50 ), $atts, 'tttc_players' );
		$query = new WP_Query( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'posts_per_page' => max( 1, absint( $atts['limit'] ) ), 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => array( array( 'key' => TTTC_Plugin::PLAYER_META_ACTIVE, 'value' => '1' ) ) ) );
		if ( ! $query->have_posts() ) {
			return '<p class="tttc-empty">' . esc_html__( 'No active players found.', 'table-tennis-tournament-for-clubs' ) . '</p>';
		}
		$output = '<div class="tttc-players"><ul>';
		while ( $query->have_posts() ) {
			$query->the_post();
			$output .= '<li><strong>' . esc_html( get_the_title() ) . '</strong><span>' . esc_html__( 'Rating:', 'table-tennis-tournament-for-clubs' ) . ' ' . esc_html( get_post_meta( get_the_ID(), TTTC_Plugin::PLAYER_META_RATING, true ) ) . '</span></li>';
		}
		wp_reset_postdata();
		return $output . '</ul></div>';
	}

	private function assigned_players_markup( $tournament_id ) {
		global $wpdb;
		$player_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d ORDER BY created_at ASC', $tournament_id ) );
		if ( empty( $player_ids ) ) {
			return '<p class="tttc-no-players">' . esc_html__( 'Players will be announced soon.', 'table-tennis-tournament-for-clubs' ) . '</p>';
		}
		$output = '<h4>' . esc_html__( 'Players', 'table-tennis-tournament-for-clubs' ) . '</h4><ul class="tttc-assigned-players">';
		foreach ( $player_ids as $player_id ) {
			$output .= '<li>' . esc_html( get_the_title( $player_id ) ) . '<span>' . esc_html__( 'Rating:', 'table-tennis-tournament-for-clubs' ) . ' ' . esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) ) . '</span></li>';
		}
		return $output . '</ul>';
	}
}
