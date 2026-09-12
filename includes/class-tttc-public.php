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
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_tournament_page' ) );
	}

	public static function register_rewrite() {
		add_rewrite_rule( '^toernooi/([^/]+)/([0-9]{2}-[0-9]{2}-[0-9]{4})/?$', 'index.php?tttc_tournament=$matches[1]&tttc_tournament_date=$matches[2]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'tttc_tournament';
		$vars[] = 'tttc_tournament_date';

		return $vars;
	}

	public function tournament_url( $tournament_id ) {
		$date = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		if ( ! $date ) {
			return '';
		}

		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object ) {
			return '';
		}

		return home_url( user_trailingslashit( 'toernooi/' . get_post_field( 'post_name', $tournament_id ) . '/' . $date_object->format( 'd-m-Y' ) ) );
	}

	public function render_tournament_page() {
		$slug = get_query_var( 'tttc_tournament' );
		$date = get_query_var( 'tttc_tournament_date' );
		if ( ! $slug || ! $date ) {
			return;
		}

		$query = new WP_Query( array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'post_status' => 'publish', 'name' => sanitize_title( $slug ), 'posts_per_page' => 1 ) );
		if ( ! $query->have_posts() ) {
			return;
		}

		$query->the_post();
		$tournament_id = get_the_ID();
		$stored_date   = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$stored_date_object = DateTime::createFromFormat( 'Y-m-d', $stored_date );
		$expected_date      = $stored_date_object ? $stored_date_object->format( 'd-m-Y' ) : '';
		wp_reset_postdata();
		if ( $expected_date !== $date || get_post_field( 'post_name', $tournament_id ) !== sanitize_title( $slug ) ) {
			return;
		}

		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
		get_header();
		$this->render_tournament_detail( $tournament_id );
		get_footer();
		exit;
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
			$title = '<a href="' . esc_url( $this->tournament_url( $post_id ) ) . '">' . esc_html( get_the_title() ) . '</a>';
			$output .= '<article class="tttc-tournament"><h3>' . $title . '</h3><dl><dt>' . esc_html__( 'Date', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true ) ) . '</dd><dt>' . esc_html__( 'Best of', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true ) ) . '</dd><dt>' . esc_html__( 'Status', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( $label ) . '</dd></dl>' . $this->assigned_players_markup( $post_id ) . '</article>';
		}
		wp_reset_postdata();
		return $output . '</div>';
	}

	private function render_tournament_detail( $tournament_id ) {
		$title       = get_the_title( $tournament_id );
		$stored_date = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$players     = $this->assigned_players( $tournament_id );
		$group_count = $this->group_count( count( $players ) );
		?>
		<main class="tttc-public-tournament">
			<div class="tttc-public-tournament__inner">
				<header class="tttc-public-tournament__header">
					<p class="tttc-public-tournament__eyebrow"><?php esc_html_e( 'Table tennis tournament', 'table-tennis-tournament-for-clubs' ); ?></p>
					<h1><?php echo esc_html( $title ); ?></h1>
					<p class="tttc-public-tournament__date"><?php echo esc_html( $this->display_date( $stored_date ) ); ?></p>
				</header>
				<details class="tttc-public-tournament__players">
					<summary><span><?php esc_html_e( 'Players', 'table-tennis-tournament-for-clubs' ); ?></span> <span class="tttc-players-expand-label"><?php esc_html_e( 'expand', 'table-tennis-tournament-for-clubs' ); ?></span><span class="tttc-players-collapse-label"><?php esc_html_e( 'collapse', 'table-tennis-tournament-for-clubs' ); ?></span></summary>
					<?php if ( empty( $players ) ) : ?>
						<p><?php esc_html_e( 'Players will be announced soon.', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php else : ?>
						<ol class="tttc-public-player-list">
							<?php foreach ( $players as $player ) : ?>
								<li><span><?php echo esc_html( $player->post_title ); ?></span><strong><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></strong></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</details>
				<?php if ( $group_count ) : ?>
					<section class="tttc-public-groups" aria-labelledby="tttc-groups-heading">
						<h2 id="tttc-groups-heading"><?php esc_html_e( 'Groups and matches', 'table-tennis-tournament-for-clubs' ); ?></h2>
						<div class="tttc-public-group-grid">
							<?php foreach ( $this->build_groups( $players, $group_count ) as $index => $group ) : ?>
								<section class="tttc-public-group">
									<h3><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $index + 1 ) ); ?></h3>
									<ul class="tttc-public-group__players">
										<?php foreach ( $group as $player ) : ?><li><?php echo esc_html( $player->post_title ); ?></li><?php endforeach; ?>
									</ul>
									<div class="tttc-public-round-grid">
									<?php foreach ( $this->group_matches( $group ) as $round_number => $round ) : ?>
										<div class="tttc-public-round">
											<h4 class="tttc-public-round-title"><?php echo esc_html( sprintf( __( 'Round %d', 'table-tennis-tournament-for-clubs' ), $round_number + 1 ) ); ?></h4>
											<table class="tttc-public-matches"><thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead><tbody>
											<?php foreach ( $round as $match_number => $match ) : ?><tr><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $match[0]->post_title ); ?></td><td><?php echo esc_html( $match[1]->post_title ); ?></td></tr><?php endforeach; ?>
											</tbody></table>
										</div>
									<?php endforeach; ?>
									</div>
								</section>
							<?php endforeach; ?>
						</div>
					</section>
				<?php elseif ( count( $players ) ) : ?>
					<p class="tttc-public-notice"><?php esc_html_e( 'A match schedule is available for tournaments with 4 to 28 active players.', 'table-tennis-tournament-for-clubs' ); ?></p>
				<?php endif; ?>
			</div>
		</main>
		<?php
	}

	private function assigned_players( $tournament_id ) {
		$player_ids = $this->assigned_player_ids( $tournament_id );
		if ( empty( $player_ids ) ) {
			return array();
		}

		$players = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'post__in' => $player_ids, 'numberposts' => -1 ) );
		$players = array_filter( $players, function ( $player ) {
			return '1' === get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true );
		} );
		usort( $players, function ( $first, $second ) {
			$rating_difference = absint( get_post_meta( $second->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ) - absint( get_post_meta( $first->ID, TTTC_Plugin::PLAYER_META_RATING, true ) );
			if ( 0 !== $rating_difference ) {
				return $rating_difference;
			}

			$title_difference = strcasecmp( $first->post_title, $second->post_title );
			return 0 !== $title_difference ? $title_difference : $first->ID - $second->ID;
		} );

		return $players;
	}

	private function assigned_player_ids( $tournament_id ) {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d ORDER BY created_at ASC', $tournament_id ) ) );
	}

	private function group_count( $player_count ) {
		if ( $player_count >= 4 && $player_count <= 7 ) {
			return 1;
		}
		if ( $player_count >= 8 && $player_count <= 14 ) {
			return 2;
		}
		if ( $player_count >= 15 && $player_count <= 19 ) {
			return 3;
		}
		if ( $player_count >= 20 && $player_count <= 28 ) {
			return 4;
		}

		return 0;
	}

	private function build_groups( $players, $group_count ) {
		$groups = array_fill( 0, $group_count, array() );
		$direction = 1;
		$group_index = 0;
		foreach ( $players as $player ) {
			$groups[ $group_index ][] = $player;
			$group_index += $direction;
			if ( $group_index >= $group_count ) {
				$group_index = $group_count - 1;
				$direction = -1;
			} elseif ( $group_index < 0 ) {
				$group_index = 0;
				$direction = 1;
			}
		}

		return $groups;
	}

	private function group_matches( $group ) {
		$players = array_values( $group );
		if ( count( $players ) % 2 ) {
			$players[] = null;
		}

		$rounds      = array();
		$player_count = count( $players );
		for ( $round_number = 0; $round_number < $player_count - 1; $round_number++ ) {
			$round = array();
			for ( $match_number = 0; $match_number < $player_count / 2; $match_number++ ) {
				$first  = $players[ $match_number ];
				$second = $players[ $player_count - 1 - $match_number ];
				if ( $first && $second ) {
					$round[] = array( $first, $second );
				}
			}
			$rounds[] = $round;
			$rotating_player = array_pop( $players );
			array_splice( $players, 1, 0, array( $rotating_player ) );
		}

		return $rounds;
	}

	private function display_date( $date ) {
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );

		return $date_object ? $date_object->format( 'd-m-Y' ) : $date;
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
