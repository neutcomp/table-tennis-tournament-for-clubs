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
	private static $instance;
	private $signup_error = '';
	private $signup_form = array();

	public function __construct() {
		self::$instance = $this;
		add_shortcode( 'tttc_tournaments', array( $this, 'tournaments_shortcode' ) );
		add_shortcode( 'tttc_players', array( $this, 'players_shortcode' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_action( 'init', array( $this, 'register_rewrite' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_action( 'template_redirect', array( $this, 'render_tournament_page' ) );
		add_action( 'template_redirect', array( $this, 'render_player_page' ) );
	}

	public function enqueue_assets() {
		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
	}

	public static function instance() {
		return self::$instance;
	}

	public static function register_rewrite() {
		add_rewrite_rule( '^toernooi/([^/]+)/([0-9]{2}-[0-9]{2}-[0-9]{4})/?$', 'index.php?tttc_tournament=$matches[1]&tttc_tournament_date=$matches[2]', 'top' );
		add_rewrite_rule( '^speler/([^/]+)/([0-9]+)/?$', 'index.php?tttc_player=$matches[1]&tttc_player_id=$matches[2]', 'top' );
	}

	public function query_vars( $vars ) {
		$vars[] = 'tttc_tournament';
		$vars[] = 'tttc_tournament_date';
		$vars[] = 'tttc_player';
		$vars[] = 'tttc_player_id';

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

	public function player_url( $player_id, $tournament_id = 0 ) {
		if ( TTTC_Plugin::PLAYER_POST_TYPE !== get_post_type( $player_id ) ) {
			return '';
		}

		$url = home_url( user_trailingslashit( 'speler/' . get_post_field( 'post_name', $player_id ) . '/' . absint( $player_id ) ) );
		if ( $tournament_id && TTTC_Plugin::TOURNAMENT_POST_TYPE === get_post_type( $tournament_id ) ) {
			$url = add_query_arg( 'tttc_tournament_id', absint( $tournament_id ), $url );
		}

		return $url;
	}

	public function tournaments_page_url() {
		global $wpdb;
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[tttc_tournaments' ) . '%'
			)
		);

		$url = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		return apply_filters( 'tttc_tournaments_page_url', $url, $page_id );
	}

	public function players_page_url() {
		global $wpdb;
		$page_id = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT ID FROM {$wpdb->posts} WHERE post_type = 'page' AND post_status = 'publish' AND post_content LIKE %s ORDER BY ID ASC LIMIT 1",
				'%' . $wpdb->esc_like( '[tttc_players' ) . '%'
			)
		);

		$url = $page_id ? get_permalink( $page_id ) : home_url( '/' );

		return apply_filters( 'tttc_players_page_url', $url, $page_id );
	}

	private function render_breadcrumbs( $items ) {
		if ( empty( $items ) ) {
			return;
		}
		?>
		<nav class="tttc-breadcrumbs" aria-label="<?php esc_attr_e( 'Breadcrumbs', 'table-tennis-tournament-for-clubs' ); ?>" style="margin-bottom: 20px;">
			<div class="tttc-breadcrumbs__list" itemscope itemtype="https://schema.org/BreadcrumbList" style="display: flex; flex-wrap: wrap; align-items: center; gap: 6px 8px; margin: 0; padding: 0; font-size: 14px; line-height: 1.4;">
				<?php foreach ( $items as $index => $item ) :
					$position = $index + 1;
					$is_last  = $position === count( $items );
					?>
					<span class="tttc-breadcrumbs__item" itemprop="itemListElement" itemscope itemtype="https://schema.org/ListItem" style="display: inline-flex; align-items: center; gap: 6px 8px;">
						<?php if ( ! empty( $item['url'] ) && ! $is_last ) : ?>
							<a class="tttc-breadcrumbs__link" href="<?php echo esc_url( $item['url'] ); ?>" itemprop="item"><span itemprop="name"><?php echo esc_html( $item['label'] ); ?></span></a>
							<span class="tttc-breadcrumbs__separator" aria-hidden="true" style="color: #8c8f94; font-size: 13px; user-select: none;">&gt;</span>
						<?php else : ?>
							<span class="tttc-breadcrumbs__current" aria-current="page" itemprop="name" style="color: #646970; font-weight: 500;"><?php echo esc_html( $item['label'] ); ?></span>
						<?php endif; ?>
						<meta itemprop="position" content="<?php echo esc_attr( $position ); ?>">
					</span>
				<?php endforeach; ?>
			</div>
		</nav>
		<?php
	}

	public function tournament_schedule( $tournament_id ) {
		$players     = $this->assigned_players( $tournament_id );
		$group_count = $this->group_count( count( $players ), $tournament_id );
		$schedule    = array();

		foreach ( $this->build_groups( $players, $group_count ) as $group ) {
			$schedule[] = array(
				'players' => $group,
				'rounds'  => $this->group_matches( $group ),
			);
		}

		return $schedule;
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

		$this->handle_signup( $tournament_id );

		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
		wp_enqueue_script( 'tttc-public', TTTC_URL . 'assets/public.js', array(), TTTC_VERSION, true );
		get_header();
		$this->render_tournament_detail( $tournament_id );
		get_footer();
		exit;
	}

	public function render_player_page() {
		$slug      = get_query_var( 'tttc_player' );
		$player_id = absint( get_query_var( 'tttc_player_id' ) );
		if ( ! $slug || ! $player_id || TTTC_Plugin::PLAYER_POST_TYPE !== get_post_type( $player_id ) || 'publish' !== get_post_status( $player_id ) ) {
			return;
		}

		if ( get_post_field( 'post_name', $player_id ) !== sanitize_title( $slug ) ) {
			return;
		}

		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
		get_header();
		$this->render_player_detail( $player_id );
		get_footer();
		exit;
	}

	private function render_player_detail( $player_id ) {
		$player_name = get_the_title( $player_id );
		$gender      = get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_GENDER, true );
		$type        = get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_TYPE, true );
		$gender_label = 'female' === $gender ? __( 'Female', 'table-tennis-tournament-for-clubs' ) : __( 'Male', 'table-tennis-tournament-for-clubs' );
		$gender_abbreviation = 'female' === $gender ? _x( 'F', 'female gender abbreviation', 'table-tennis-tournament-for-clubs' ) : _x( 'M', 'male gender abbreviation', 'table-tennis-tournament-for-clubs' );
		$type         = 'youth' === $type ? __( 'Youth', 'table-tennis-tournament-for-clubs' ) : __( 'Senior', 'table-tennis-tournament-for-clubs' );
		$tournaments  = $this->player_tournaments( $player_id );
		$tournament_param = isset( $_GET['tttc_tournament_id'] ) ? absint( wp_unslash( $_GET['tttc_tournament_id'] ) ) : ( isset( $_GET['tournament_id'] ) ? absint( wp_unslash( $_GET['tournament_id'] ) ) : 0 );
		$active_tournament = null;
		if ( $tournament_param && TTTC_Plugin::TOURNAMENT_POST_TYPE === get_post_type( $tournament_param ) && 'publish' === get_post_status( $tournament_param ) ) {
			$active_tournament = get_post( $tournament_param );
		} elseif ( ! empty( $tournaments ) ) {
			$active_tournament = $tournaments[0];
		}

		$breadcrumbs = array(
			array(
				'label' => __( 'Home', 'table-tennis-tournament-for-clubs' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Tournaments', 'table-tennis-tournament-for-clubs' ),
				'url'   => $this->tournaments_page_url(),
			),
		);

		if ( $active_tournament ) {
			$breadcrumbs[] = array(
				'label' => get_the_title( $active_tournament->ID ),
				'url'   => $this->tournament_url( $active_tournament->ID ),
			);
		}

		$breadcrumbs[] = array(
			'label' => $player_name,
			'url'   => '',
		);
		?>
		<main class="tttc-public-player">
			<div class="tttc-public-player__inner">
				<?php $this->render_breadcrumbs( $breadcrumbs ); ?>
				<header class="tttc-public-player__header">
					<p class="tttc-public-player__eyebrow"><?php esc_html_e( 'Table tennis player', 'table-tennis-tournament-for-clubs' ); ?></p>
					<h1><?php echo esc_html( $player_name . ' (' . $gender_abbreviation . ')' ); ?></h1>
					<dl class="tttc-public-player__details">
						<div><dt><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></dt><dd><?php echo esc_html( $gender_label ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></dt><dd><?php echo esc_html( $type ); ?></dd></div>
						<div><dt><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></dt><dd><?php echo esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></dd></div>
					</dl>
				</header>
				<section class="tttc-public-player__tournaments" aria-labelledby="tttc-player-tournaments-heading">
					<h2 id="tttc-player-tournaments-heading"><?php esc_html_e( 'Played tournaments', 'table-tennis-tournament-for-clubs' ); ?></h2>
					<?php if ( empty( $tournaments ) ) : ?>
						<p class="tttc-public-notice"><?php esc_html_e( 'No published tournaments found.', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php else : ?>
						<ul class="tttc-public-player__tournament-list">
							<?php foreach ( $tournaments as $tournament ) : $position = $this->player_tournament_position( $player_id, $tournament->ID ); ?>
								<li>
									<div><a href="<?php echo esc_url( $this->tournament_url( $tournament->ID ) ); ?>"><?php echo esc_html( get_the_title( $tournament->ID ) ); ?></a> <span class="tttc-public-player__tournament-date"><?php echo esc_html( $this->display_date( get_post_meta( $tournament->ID, TTTC_Plugin::TOURNAMENT_META_DATE, true ) ) ); ?></span></div>
									<?php if ( $position ) : ?><span><?php echo esc_html( sprintf( __( 'Position: %d', 'table-tennis-tournament-for-clubs' ), $position ) ); ?></span><?php endif; ?>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</section>
			</div>
		</main>
		<?php
	}

	public function tournaments_shortcode( $atts ) {
		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
		$atts = shortcode_atts( array( 'id' => 0, 'limit' => 10 ), $atts, 'tttc_tournaments' );
		$args = array(
			'post_type'      => TTTC_Plugin::TOURNAMENT_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => max( 1, absint( $atts['limit'] ) ),
			'orderby'        => 'meta_value',
			'meta_key'       => TTTC_Plugin::TOURNAMENT_META_DATE,
			'order'          => 'ASC',
			'meta_query'     => array(
				array(
					'key'     => TTTC_Plugin::TOURNAMENT_META_STATUS,
					'value'   => array( 'draft', 'cancelled' ),
					'compare' => 'NOT IN',
				),
			),
		);
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
			$post_id     = get_the_ID();
			$status      = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			$title       = '<a href="' . esc_url( $this->tournament_url( $post_id ) ) . '">' . esc_html( get_the_title() ) . '</a>';
			$action_link = '';
			if ( 'upcoming' === $status ) {
				$action_link = '<p class="tttc-tournament-action"><a href="' . esc_url( $this->tournament_url( $post_id ) ) . '" class="tttc-tournament-btn tttc-tournament-btn--upcoming">' . esc_html__( 'Signup now!', 'table-tennis-tournament-for-clubs' ) . ' &rarr;</a></p>';
			} elseif ( 'active' === $status ) {
				$action_link = '<p class="tttc-tournament-action"><a href="' . esc_url( $this->tournament_url( $post_id ) ) . '" class="tttc-tournament-btn tttc-tournament-btn--active">' . esc_html__( 'Show live score', 'table-tennis-tournament-for-clubs' ) . ' &rarr;</a></p>';
			} elseif ( 'completed' === $status ) {
				$action_link = '<p class="tttc-tournament-action"><a href="' . esc_url( $this->tournament_url( $post_id ) ) . '" class="tttc-tournament-btn tttc-tournament-btn--completed">' . esc_html__( 'Show results', 'table-tennis-tournament-for-clubs' ) . ' &rarr;</a></p>';
			}
			$output .= '<article class="tttc-tournament tttc-tournament--' . esc_attr( $status ) . '"><header class="tttc-tournament__header"><h3>' . $title . '</h3></header><dl class="tttc-tournament__details"><div class="tttc-tournament__detail-item"><dt>' . esc_html__( 'Date', 'table-tennis-tournament-for-clubs' ) . '</dt><dd>' . esc_html( $this->display_date( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true ) ) ) . '</dd></div></dl>' . $action_link . '</article>';
		}
		wp_reset_postdata();
		return $output . '</div>';
	}

	private function render_tournament_detail( $tournament_id ) {
		$title       = get_the_title( $tournament_id );
		$stored_date = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$status      = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
		$players     = $this->assigned_players( $tournament_id );
		$player_ratings = $this->assigned_player_ratings( $tournament_id );
		$schedule    = $this->tournament_schedule( $tournament_id );
		$games       = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$games       = in_array( (string) $games, array( '3', '5' ), true ) ? (int) $games : 3;
		$scores      = $this->saved_scores( $tournament_id );
		$competition = TTTC_Competition::calculate( $schedule, $scores, $games );
		$page_url    = $this->tournament_url( $tournament_id );
		$qr_url      = $page_url ? 'https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=' . rawurlencode( $page_url ) : '';
		$breadcrumbs = array(
			array(
				'label' => __( 'Home', 'table-tennis-tournament-for-clubs' ),
				'url'   => home_url( '/' ),
			),
			array(
				'label' => __( 'Tournaments', 'table-tennis-tournament-for-clubs' ),
				'url'   => $this->tournaments_page_url(),
			),
			array(
				'label' => $title,
				'url'   => '',
			),
		);
		?>
		<main class="tttc-public-tournament">
			<div class="tttc-public-tournament__inner">
				<?php $this->render_breadcrumbs( $breadcrumbs ); ?>
				<header class="tttc-public-tournament__header">
					<div class="tttc-public-tournament__header-text">
						<p class="tttc-public-tournament__eyebrow"><?php esc_html_e( 'Table tennis tournament', 'table-tennis-tournament-for-clubs' ); ?></p>
						<h1><?php echo esc_html( $title ); ?></h1>
						<p class="tttc-public-tournament__date"><?php echo esc_html( $this->display_date( $stored_date ) ); ?></p>
					</div>
					<?php if ( $qr_url ) : ?>
						<img class="tttc-public-tournament__qr" src="<?php echo esc_url( $qr_url ); ?>" width="200" height="200" alt="<?php esc_attr_e( 'QR code linking to this tournament page', 'table-tennis-tournament-for-clubs' ); ?>" loading="lazy">
					<?php endif; ?>
				</header>
				<?php if ( 'upcoming' === $status ) : $this->render_signup_form( $tournament_id ); endif; ?>
				<details class="tttc-public-tournament__players">
					<summary><span><?php esc_html_e( 'Players', 'table-tennis-tournament-for-clubs' ); ?></span> <span class="tttc-players-expand-label"><?php esc_html_e( 'expand', 'table-tennis-tournament-for-clubs' ); ?></span><span class="tttc-players-collapse-label"><?php esc_html_e( 'collapse', 'table-tennis-tournament-for-clubs' ); ?></span></summary>
					<?php if ( empty( $players ) ) : ?>
						<p><?php esc_html_e( 'Players will be announced soon.', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php else : ?>
						<div class="tttc-public-player-list-header" aria-hidden="true"><span><?php esc_html_e( 'Name', 'table-tennis-tournament-for-clubs' ); ?></span><span><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></span></div>
						<ol class="tttc-public-player-list">
							<?php foreach ( $players as $player ) : ?>
								<li><span><a href="<?php echo esc_url( $this->player_url( $player->ID, $tournament_id ) ); ?>"><?php echo esc_html( $player->post_title ); ?></a></span><strong><?php echo esc_html( isset( $player_ratings[ $player->ID ] ) ? $player_ratings[ $player->ID ] : 0 ); ?></strong></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</details>
				<?php if ( in_array( $status, array( 'active', 'completed' ), true ) && ! empty( $schedule ) ) : ?>
					<section class="tttc-public-groups" aria-labelledby="tttc-groups-heading">
						<h2 id="tttc-groups-heading"><?php esc_html_e( 'Groups and matches', 'table-tennis-tournament-for-clubs' ); ?></h2>
						<div class="tttc-public-group-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Groups and crossover rounds', 'table-tennis-tournament-for-clubs' ); ?>">
							<?php foreach ( $schedule as $index => $group_schedule ) : $tab_id = 'tttc-group-tab-' . ( $index + 1 ); $panel_id = 'tttc-group-panel-' . ( $index + 1 ); ?>
								<button id="<?php echo esc_attr( $tab_id ); ?>" class="tttc-public-group-tab<?php echo 0 === $index ? ' is-active' : ''; ?>" type="button" role="tab" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-selected="<?php echo 0 === $index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $index ? '0' : '-1'; ?>"><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $index + 1 ) ); ?></button>
			<?php endforeach; ?>
							<?php if ( count( $schedule ) > 1 ) : ?>
								<?php foreach ( $competition['stages'] as $stage ) : ?>
									<?php if ( 'final' !== $stage['id'] ) : $tab_id = 'tttc-crossover-tab-' . $stage['id']; $panel_id = 'tttc-crossover-panel-' . $stage['id']; ?>
										<button id="<?php echo esc_attr( $tab_id ); ?>" class="tttc-public-group-tab" type="button" role="tab" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-selected="false" tabindex="-1"><?php echo esc_html( __( $stage['label'], 'table-tennis-tournament-for-clubs' ) ); ?></button>
									<?php endif; ?>
								<?php endforeach; ?>
								<?php foreach ( $competition['stages'] as $stage ) : ?>
									<?php if ( 'final' === $stage['id'] ) : $tab_id = 'tttc-crossover-tab-' . $stage['id']; $panel_id = 'tttc-crossover-panel-' . $stage['id']; ?>
										<button id="<?php echo esc_attr( $tab_id ); ?>" class="tttc-public-group-tab" type="button" role="tab" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-selected="false" tabindex="-1"><?php echo esc_html( __( $stage['label'], 'table-tennis-tournament-for-clubs' ) ); ?></button>
									<?php endif; ?>
								<?php endforeach; ?>
							<?php endif; ?>
							<?php if ( ! empty( $competition['places'] ) ) : ?>
								<button id="tttc-final-places-tab" class="tttc-public-group-tab" type="button" role="tab" aria-controls="tttc-final-places-panel" aria-selected="false" tabindex="-1"><?php esc_html_e( 'Final places', 'table-tennis-tournament-for-clubs' ); ?></button>
							<?php endif; ?>
						</div>
						<div class="tttc-public-group-grid">
							<?php foreach ( $schedule as $index => $group_schedule ) : ?>
								<section id="<?php echo esc_attr( 'tttc-group-panel-' . ( $index + 1 ) ); ?>" class="tttc-public-group<?php echo 0 === $index ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( 'tttc-group-tab-' . ( $index + 1 ) ); ?>"<?php echo 0 === $index ? '' : ' hidden'; ?>>
									<h3><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $index + 1 ) ); ?></h3>
									<ul class="tttc-public-group__players">
										<?php foreach ( $group_schedule['players'] as $player ) : ?><li><?php echo esc_html( $player->post_title ); ?></li><?php endforeach; ?>
									</ul>
									<div class="tttc-public-round-grid">
									<?php foreach ( $group_schedule['rounds'] as $round_number => $round ) : ?>
										<div class="tttc-public-round">
											<h4 class="tttc-public-round-title"><?php echo esc_html( sprintf( __( 'Round %d', 'table-tennis-tournament-for-clubs' ), $round_number + 1 ) ); ?></h4>
											<table class="tttc-public-matches"><thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?><th><?php esc_html_e( 'Games won', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead><tbody>
											<?php foreach ( $round as $match_number => $match ) : $match_key = $this->match_key( $match[0]->ID, $match[1]->ID ); $match_scores = isset( $scores[ $match_key ] ) ? $scores[ $match_key ] : array(); $games_won = $this->games_won( $match_scores, $games ); ?><tr><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $match[0]->post_title ); ?></td><td><?php echo esc_html( $match[1]->post_title ); ?></td><?php for ( $game = 0; $game < $games; $game++ ) : $game_score = isset( $match_scores[ $game ] ) ? $match_scores[ $game ] : array( '', '' ); ?><td><?php echo esc_html( (string) $game_score[0] . '-' . (string) $game_score[1] ); ?></td><?php endfor; ?><td><?php echo esc_html( $games_won[0] . '-' . $games_won[1] ); ?></td></tr><?php endforeach; ?>
											</tbody></table>
										</div>
									<?php endforeach; ?>
									</div>
									<?php if ( ! empty( $competition['groups'][ $index ]['complete'] ) ) : ?>
										<div class="tttc-public-round-standings">
											<h4 class="tttc-public-round-standings-title"><?php esc_html_e( 'Standings', 'table-tennis-tournament-for-clubs' ); ?></h4>
											<table class="tttc-public-matches tttc-public-standings-table">
												<thead>
													<tr>
														<th><?php esc_html_e( 'Pos', 'table-tennis-tournament-for-clubs' ); ?></th>
														<th><?php esc_html_e( 'Player', 'table-tennis-tournament-for-clubs' ); ?></th>
														<th><?php esc_html_e( 'Wins', 'table-tennis-tournament-for-clubs' ); ?></th>
														<th><?php esc_html_e( 'Losses', 'table-tennis-tournament-for-clubs' ); ?></th>
														<th><?php esc_html_e( 'Games', 'table-tennis-tournament-for-clubs' ); ?></th>
														<th><?php esc_html_e( 'Points', 'table-tennis-tournament-for-clubs' ); ?></th>
													</tr>
												</thead>
												<tbody>
													<?php foreach ( $competition['groups'][ $index ]['standings'] as $rank => $standing ) : ?>
														<tr>
															<td><?php echo esc_html( $rank + 1 ); ?></td>
															<td><a href="<?php echo esc_url( $this->player_url( $standing['player']->ID, $tournament_id ) ); ?>"><?php echo esc_html( $standing['player']->post_title ); ?></a></td>
															<td><?php echo esc_html( $standing['wins'] ); ?></td>
															<td><?php echo esc_html( $standing['losses'] ); ?></td>
															<td><?php echo esc_html( $standing['games_for'] . '-' . $standing['games_against'] ); ?></td>
															<td><?php echo esc_html( $standing['points_for'] . '-' . $standing['points_against'] ); ?></td>
														</tr>
													<?php endforeach; ?>
												</tbody>
											</table>
										</div>
									<?php endif; ?>
								</section>
							<?php endforeach; ?>
						</div>
						<?php if ( count( $schedule ) > 1 ) : ?>
							<div class="tttc-public-crossover" aria-label="<?php esc_attr_e( 'Crossover rounds', 'table-tennis-tournament-for-clubs' ); ?>">
								<?php foreach ( $competition['stages'] as $stage ) : $panel_id = 'tttc-crossover-panel-' . $stage['id']; ?>
									<section id="<?php echo esc_attr( $panel_id ); ?>" class="tttc-public-group tttc-public-crossover-stage" role="tabpanel" aria-labelledby="<?php echo esc_attr( 'tttc-crossover-tab-' . $stage['id'] ); ?>" hidden>
										<h3><?php echo esc_html( __( $stage['label'], 'table-tennis-tournament-for-clubs' ) ); ?></h3>
										<table class="tttc-public-matches"><thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?><th><?php esc_html_e( 'Games won', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead><tbody>
										<?php foreach ( $stage['matches'] as $match_number => $match ) : $available = $match['players'][0] && $match['players'][1]; $match_scores = isset( $scores[ $match['score_key'] ] ) ? $scores[ $match['score_key'] ] : array(); $games_won = $this->games_won( $match_scores, $games ); ?>
											<tr><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $available ? $match['players'][0]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td><td><?php echo esc_html( $available ? $match['players'][1]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td><?php for ( $game = 0; $game < $games; $game++ ) : $game_score = isset( $match_scores[ $game ] ) ? $match_scores[ $game ] : array( '', '' ); ?><td><?php echo esc_html( (string) $game_score[0] . '-' . (string) $game_score[1] ); ?></td><?php endfor; ?><td><?php echo esc_html( $games_won[0] . '-' . $games_won[1] ); ?></td></tr>
										<?php endforeach; ?></tbody></table>
									</section>
								<?php endforeach; ?>
							</div>
						<?php endif; ?>
						<?php if ( ! empty( $competition['places'] ) ) : ?>
							<section id="tttc-final-places-panel" class="tttc-public-group" role="tabpanel" aria-labelledby="tttc-final-places-tab" hidden>
								<h3><?php esc_html_e( 'Final places', 'table-tennis-tournament-for-clubs' ); ?></h3>
								<ol class="tttc-public-places"><?php foreach ( $competition['places'] as $place ) : ?><li><?php echo esc_html( $place['player']->post_title ); ?></li><?php endforeach; ?></ol>
							</section>
						<?php endif; ?>
					</section>
				<?php elseif ( in_array( $status, array( 'active', 'completed' ), true ) && count( $players ) ) :
					$format_ranges = TTTC_Plugin::get_format_ranges( $tournament_id );
					$min_players   = isset( $format_ranges[1]['min'] ) ? $format_ranges[1]['min'] : 4;
					$max_players   = isset( $format_ranges[4]['max'] ) ? $format_ranges[4]['max'] : 28;
					?>
					<p class="tttc-public-notice"><?php echo esc_html( sprintf( __( 'A match schedule is available for tournaments with %1$d to %2$d active players.', 'table-tennis-tournament-for-clubs' ), $min_players, $max_players ) ); ?></p>
				<?php endif; ?>
			</div>
		</main>
		<?php
	}

	private function handle_signup( $tournament_id ) {
		$this->signup_form = array(
			'name'   => '',
			'rating' => '',
			'email'  => '',
			'gender' => 'male',
			'type'   => 'senior',
		);

		if ( ! isset( $_POST['tttc_signup_action'] ) || 'tttc_signup' !== sanitize_key( wp_unslash( $_POST['tttc_signup_action'] ) ) ) {
			return;
		}

		if ( 'upcoming' !== get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true ) || ! isset( $_POST['tttc_signup_tournament'] ) || $tournament_id !== absint( $_POST['tttc_signup_tournament'] ) ) {
			return;
		}

		$this->signup_form = array(
			'name'   => isset( $_POST['tttc_signup_name'] ) ? $this->normalize_name( wp_unslash( $_POST['tttc_signup_name'] ) ) : '',
			'rating' => isset( $_POST['tttc_signup_rating'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_signup_rating'] ) ) : '',
			'email'  => isset( $_POST['tttc_signup_email'] ) ? strtolower( sanitize_email( wp_unslash( $_POST['tttc_signup_email'] ) ) ) : '',
			'gender' => isset( $_POST['tttc_signup_gender'] ) ? sanitize_key( wp_unslash( $_POST['tttc_signup_gender'] ) ) : 'male',
			'type'   => isset( $_POST['tttc_signup_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_signup_type'] ) ) : 'senior',
		);

		if ( ! isset( $_POST['tttc_signup_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_signup_nonce'] ) ), 'tttc_signup_' . $tournament_id ) ) {
			$this->signup_error = __( 'The signup could not be verified. Please try again.', 'table-tennis-tournament-for-clubs' );
			return;
		}

		if ( '' === $this->signup_form['name'] ) {
			$this->signup_error = __( 'Please enter your name.', 'table-tennis-tournament-for-clubs' );
			return;
		}
		if ( '' !== $this->signup_form['rating'] && ! preg_match( '/^[0-9]+$/', $this->signup_form['rating'] ) ) {
			$this->signup_error = __( 'Please enter a valid nonnegative rating.', 'table-tennis-tournament-for-clubs' );
			return;
		}
		if ( '' === $this->signup_form['email'] || ! is_email( $this->signup_form['email'] ) ) {
			$this->signup_error = __( 'Please enter a valid email address so we can confirm your signup.', 'table-tennis-tournament-for-clubs' );
			return;
		}
		if ( ! in_array( $this->signup_form['gender'], array( 'male', 'female' ), true ) ) {
			$this->signup_error = __( 'Please select a valid gender.', 'table-tennis-tournament-for-clubs' );
			return;
		}
		if ( ! in_array( $this->signup_form['type'], array( 'senior', 'youth' ), true ) ) {
			$this->signup_error = __( 'Please select a valid player type.', 'table-tennis-tournament-for-clubs' );
			return;
		}

		$tournament_type = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
		if ( ! in_array( $tournament_type, array( 'senior', 'youth', 'both' ), true ) ) {
			$tournament_type = 'both';
		}
		if ( 'both' !== $tournament_type && $tournament_type !== $this->signup_form['type'] ) {
			$this->signup_error = sprintf( __( 'This tournament is only open to %s players.', 'table-tennis-tournament-for-clubs' ), 'senior' === $tournament_type ? __( 'Senior', 'table-tennis-tournament-for-clubs' ) : __( 'Youth', 'table-tennis-tournament-for-clubs' ) );
			return;
		}

		$players = get_posts( array(
			'post_type'      => TTTC_Plugin::PLAYER_POST_TYPE,
			'post_status'    => 'publish',
			'posts_per_page' => 1,
			'title'          => $this->signup_form['name'],
			'meta_query'     => array(
				array(
					'key'     => TTTC_Plugin::PLAYER_META_EMAIL,
					'value'   => $this->signup_form['email'],
					'compare' => '=',
				),
			),
		) );
		$player = ! empty( $players ) ? $players[0] : null;

		if ( $player ) {
			$player_type = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
			$player_type = in_array( $player_type, array( 'senior', 'youth' ), true ) ? $player_type : 'senior';
			if ( 'both' !== $tournament_type && $tournament_type !== $player_type ) {
				$this->signup_error = __( 'An existing player with this name and email has a type that is not allowed for this tournament.', 'table-tennis-tournament-for-clubs' );
				return;
			}
			update_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, '' === $this->signup_form['rating'] ? '' : absint( $this->signup_form['rating'] ) );
			update_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_ACTIVE, '1' );
		} else {
			$player_id = wp_insert_post( array(
				'post_type'   => TTTC_Plugin::PLAYER_POST_TYPE,
				'post_status' => 'publish',
				'post_title'  => $this->signup_form['name'],
			), true );
			if ( is_wp_error( $player_id ) ) {
				$this->signup_error = __( 'The player could not be saved. Please try again.', 'table-tennis-tournament-for-clubs' );
				return;
			}
			$player = get_post( $player_id );
			update_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, '' === $this->signup_form['rating'] ? '' : absint( $this->signup_form['rating'] ) );
			update_post_meta( $player_id, TTTC_Plugin::PLAYER_META_EMAIL, $this->signup_form['email'] );
			update_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, '1' );
			update_post_meta( $player_id, TTTC_Plugin::PLAYER_META_GENDER, $this->signup_form['gender'] );
			update_post_meta( $player_id, TTTC_Plugin::PLAYER_META_TYPE, $this->signup_form['type'] );
		}

		global $wpdb;
		$table    = TTTC_Plugin::table_name();
		$assigned = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM ' . $table . ' WHERE tournament_id = %d AND player_id = %d LIMIT 1', $tournament_id, $player->ID ) );
		$next_seed = (int) $wpdb->get_var( $wpdb->prepare( 'SELECT COALESCE( MAX( seed ), 0 ) FROM ' . $table . ' WHERE tournament_id = %d', $tournament_id ) ) + 1;
		$rating    = '' === $this->signup_form['rating'] ? null : absint( $this->signup_form['rating'] );
		if ( ! $assigned && false === $wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'player_id' => $player->ID, 'rating' => $rating, 'seed' => $next_seed, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%d', '%d', '%s' ) ) ) {
			$this->signup_error = __( 'The player was saved, but could not be added to the tournament. Please try again.', 'table-tennis-tournament-for-clubs' );
			return;
		}

		$this->send_signup_email( $tournament_id, $this->signup_form['email'] );

		wp_safe_redirect( add_query_arg( 'tttc_signup', 'success', $this->tournament_url( $tournament_id ) ) );
		exit;
	}

	private function render_signup_form( $tournament_id ) {
		$form = wp_parse_args( $this->signup_form, array( 'name' => '', 'rating' => '', 'email' => '', 'gender' => 'male', 'type' => 'senior' ) );
		?>
		<section class="tttc-public-tournament__signup" aria-labelledby="tttc-signup-heading">
			<h2 id="tttc-signup-heading"><?php esc_html_e( 'Sign up for this tournament', 'table-tennis-tournament-for-clubs' ); ?></h2>
			<?php if ( isset( $_GET['tttc_signup'] ) && 'success' === sanitize_key( wp_unslash( $_GET['tttc_signup'] ) ) ) : ?><p class="tttc-public-notice tttc-public-notice--success" role="status"><?php esc_html_e( 'Your signup was successful.', 'table-tennis-tournament-for-clubs' ); ?></p><?php endif; ?>
			<?php if ( $this->signup_error ) : ?><p class="tttc-public-notice" role="alert"><?php echo esc_html( $this->signup_error ); ?></p><?php endif; ?>
			<form method="post" action="<?php echo esc_url( $this->tournament_url( $tournament_id ) ); ?>" class="tttc-public-signup-form">
				<input type="hidden" name="tttc_signup_action" value="tttc_signup"><input type="hidden" name="tttc_signup_tournament" value="<?php echo esc_attr( $tournament_id ); ?>"><?php wp_nonce_field( 'tttc_signup_' . $tournament_id, 'tttc_signup_nonce' ); ?>
				<div class="tttc-public-signup-form__grid">
					<p><label for="tttc-signup-name"><?php esc_html_e( 'Name', 'table-tennis-tournament-for-clubs' ); ?> <span aria-hidden="true">*</span></label><input type="text" id="tttc-signup-name" name="tttc_signup_name" value="<?php echo esc_attr( $form['name'] ); ?>" required></p>
					<p><label for="tttc-signup-rating"><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?> (<a href="https://ttapp.nl/">TTapp.nl</a>)</label><input type="number" min="0" step="1" id="tttc-signup-rating" name="tttc_signup_rating" value="<?php echo esc_attr( $form['rating'] ); ?>"></p>
					<p><label for="tttc-signup-email"><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?> <span aria-hidden="true">*</span></label><input type="email" id="tttc-signup-email" name="tttc_signup_email" value="<?php echo esc_attr( $form['email'] ); ?>" required></p>
					<p><label for="tttc-signup-gender"><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></label><select id="tttc-signup-gender" name="tttc_signup_gender"><option value="male" <?php selected( $form['gender'], 'male' ); ?>><?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></option><option value="female" <?php selected( $form['gender'], 'female' ); ?>><?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
					<p><label for="tttc-signup-type"><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></label><select id="tttc-signup-type" name="tttc_signup_type"><option value="senior" <?php selected( $form['type'], 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option><option value="youth" <?php selected( $form['type'], 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
				</div>
				<p><button type="submit"><?php esc_html_e( 'Sign up', 'table-tennis-tournament-for-clubs' ); ?></button></p>
			</form>
		</section>
		<?php
	}

	private function normalize_name( $name ) {
		$name = sanitize_text_field( $name );

		return preg_replace( '/\s+/', ' ', trim( $name ) );
	}

	private function send_signup_email( $tournament_id, $recipient ) {
		$from    = sanitize_email( get_option( TTTC_Plugin::OPTION_EMAIL_FROM, get_option( 'admin_email' ) ) );
		$subject = get_option( TTTC_Plugin::OPTION_EMAIL_SUBJECT, __( 'Signup confirmed for [tournament-name]', 'table-tennis-tournament-for-clubs' ) );
		$body    = get_option( TTTC_Plugin::OPTION_EMAIL_BODY, __( "Hello,\n\nYour signup for [tournament-name] on [tournament-date] has been received.\n\nView the tournament: [tournament-link]\n\nWe look forward to seeing you.", 'table-tennis-tournament-for-clubs' ) );
		$replacements = array(
			'[tournament-name]' => get_the_title( $tournament_id ),
			'[tournament-date]' => $this->display_date( get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_DATE, true ) ),
			'[tournament-link]' => $this->tournament_url( $tournament_id ),
		);
		$subject = strtr( $subject, $replacements );
		$body    = strtr( $body, $replacements );
		$headers = $from ? array( 'From: ' . $from ) : array();

		wp_mail( $recipient, $subject, $body, $headers );
	}

	private function assigned_players( $tournament_id ) {
		$player_ratings = $this->assigned_player_ratings( $tournament_id );
		$player_seeds   = $this->assigned_player_seeds( $tournament_id );
		$player_ids     = array_keys( $player_ratings );
		if ( empty( $player_ids ) ) {
			return array();
		}

		$players = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'post__in' => $player_ids, 'numberposts' => -1 ) );
		$players = array_filter( $players, function ( $player ) {
			return '1' === get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true );
		} );
		// The manual seed order (set on the Tournament Players screen) takes priority over rating.
		usort( $players, function ( $first, $second ) use ( $player_ratings, $player_seeds ) {
			$first_seed  = isset( $player_seeds[ $first->ID ] ) ? $player_seeds[ $first->ID ] : null;
			$second_seed = isset( $player_seeds[ $second->ID ] ) ? $player_seeds[ $second->ID ] : null;
			if ( null !== $first_seed && null !== $second_seed ) {
				return $first_seed <=> $second_seed;
			}
			if ( null !== $first_seed || null !== $second_seed ) {
				return null !== $first_seed ? -1 : 1;
			}

			$rating_difference = ( isset( $player_ratings[ $second->ID ] ) ? $player_ratings[ $second->ID ] : 0 ) - ( isset( $player_ratings[ $first->ID ] ) ? $player_ratings[ $first->ID ] : 0 );
			if ( 0 !== $rating_difference ) {
				return $rating_difference;
			}

			$title_difference = strcasecmp( $first->post_title, $second->post_title );
			return 0 !== $title_difference ? $title_difference : $first->ID - $second->ID;
		} );

		return $players;
	}

	private function assigned_player_ratings( $tournament_id ) {
		global $wpdb;
		$assignments    = $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, rating FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d', $tournament_id ) );
		$player_ratings = array();
		foreach ( $assignments as $assignment ) {
			$player_id = (int) $assignment->player_id;
			$player_ratings[ $player_id ] = null === $assignment->rating ? absint( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) ) : (int) $assignment->rating;
		}

		return $player_ratings;
	}

	private function assigned_player_seeds( $tournament_id ) {
		global $wpdb;
		$rows  = $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, seed FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d', $tournament_id ) );
		$seeds = array();
		foreach ( $rows as $row ) {
			if ( null !== $row->seed ) {
				$seeds[ (int) $row->player_id ] = (int) $row->seed;
			}
		}

		return $seeds;
	}

	private function player_tournaments( $player_id ) {
		global $wpdb;
		$tournament_ids = array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT tournament_id FROM ' . TTTC_Plugin::table_name() . ' WHERE player_id = %d', $player_id ) ) );
		if ( empty( $tournament_ids ) ) {
			return array();
		}

		return get_posts( array(
			'post_type'      => TTTC_Plugin::TOURNAMENT_POST_TYPE,
			'post_status'    => 'publish',
			'post__in'       => $tournament_ids,
			'numberposts'    => -1,
			'orderby'        => 'meta_value',
			'meta_key'       => TTTC_Plugin::TOURNAMENT_META_DATE,
			'meta_type'      => 'DATE',
			'order'          => 'DESC',
		) );
	}

	private function tournament_places( $tournament_id ) {
		$games       = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$games       = in_array( (string) $games, array( '3', '5' ), true ) ? (int) $games : 3;
		$schedule    = $this->tournament_schedule( $tournament_id );
		$scores      = $this->saved_scores( $tournament_id );
		$competition = TTTC_Competition::calculate( $schedule, $scores, $games );
		if ( ! $this->all_games_played( $schedule, $competition, $scores, $games ) ) {
			return array();
		}

		return $competition['places'];
	}

	private function player_tournament_position( $player_id, $tournament_id ) {
		foreach ( $this->tournament_places( $tournament_id ) as $index => $place ) {
			if ( isset( $place['player']->ID ) && (int) $place['player']->ID === (int) $player_id ) {
				$position = $index + 1;
				return $position <= 3 ? $position : 0;
			}
		}

		return 0;
	}

	/**
	 * Returns the winning player's ID for a completed tournament, or 0 if there is none yet.
	 */
	public function tournament_winner_id( $tournament_id ) {
		if ( 'completed' !== get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true ) ) {
			return 0;
		}

		$places = $this->tournament_places( $tournament_id );

		return isset( $places[0]['player']->ID ) ? (int) $places[0]['player']->ID : 0;
	}

	private function all_games_played( $schedule, $competition, $scores, $games ) {
		$matches = array();
		foreach ( $schedule as $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round ) {
				foreach ( $round as $match ) {
					$matches[] = $this->match_key( $match[0]->ID, $match[1]->ID );
				}
			}
		}
		foreach ( $competition['stages'] as $stage ) {
			foreach ( $stage['matches'] as $match ) {
				if ( ! $match['players'][0] || ! $match['players'][1] ) {
					return false;
				}
				$matches[] = $match['score_key'];
			}
		}

		foreach ( $matches as $match_key ) {
			if ( ! isset( $scores[ $match_key ] ) ) {
				return false;
			}
			$games_won = $this->games_won( $scores[ $match_key ], $games );
			if ( max( $games_won ) < (int) ceil( $games / 2 ) ) {
				return false;
			}
		}

		return ! empty( $matches );
	}

	private function saved_scores( $tournament_id ) {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT match_key, scores FROM ' . TTTC_Plugin::scores_table_name() . ' WHERE tournament_id = %d', $tournament_id ) );
		$scores = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->scores, true );
			if ( ! is_array( $decoded ) ) {
				continue;
			}
			$scores[ $row->match_key ] = array();
			foreach ( $decoded as $game_score ) {
				if ( is_array( $game_score ) ) {
					$scores[ $row->match_key ][] = array( isset( $game_score[0] ) ? $game_score[0] : '', isset( $game_score[1] ) ? $game_score[1] : '' );
					continue;
				}
				$parts = explode( '-', (string) $game_score, 2 );
				$scores[ $row->match_key ][] = array( $parts[0], isset( $parts[1] ) ? $parts[1] : '' );
			}
		}

		return $scores;
	}

	private function games_won( $match_scores, $games ) {
		$won = array( 0, 0 );
		for ( $game = 0; $game < $games; $game++ ) {
			if ( ! isset( $match_scores[ $game ] ) || ! is_array( $match_scores[ $game ] ) || '' === $match_scores[ $game ][0] || '' === $match_scores[ $game ][1] ) {
				continue;
			}
			$first_score  = absint( $match_scores[ $game ][0] );
			$second_score = absint( $match_scores[ $game ][1] );
			if ( $first_score > $second_score ) {
				$won[0]++;
			} elseif ( $second_score > $first_score ) {
				$won[1]++;
			}
		}

		return $won;
	}

	private function match_key( $player_one_id, $player_two_id ) {
		$player_ids = array( absint( $player_one_id ), absint( $player_two_id ) );
		sort( $player_ids, SORT_NUMERIC );

		return $player_ids[0] . '-' . $player_ids[1];
	}

	private function assigned_player_ids( $tournament_id ) {
		global $wpdb;

		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d ORDER BY created_at ASC', $tournament_id ) ) );
	}

	private function group_count( $player_count, $tournament_id = 0 ) {
		$ranges = TTTC_Plugin::get_format_ranges( $tournament_id );
		for ( $groups = 1; $groups <= 4; $groups++ ) {
			if ( isset( $ranges[ $groups ] ) && $player_count >= $ranges[ $groups ]['min'] && $player_count <= $ranges[ $groups ]['max'] ) {
				return $groups;
			}
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
		wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array(), TTTC_VERSION );
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
		$assignments = $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, rating FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d ORDER BY created_at ASC', $tournament_id ) );
		if ( empty( $assignments ) ) {
			return '<p class="tttc-no-players">' . esc_html__( 'Players will be announced soon.', 'table-tennis-tournament-for-clubs' ) . '</p>';
		}
		$output = '<h4>' . esc_html__( 'Players', 'table-tennis-tournament-for-clubs' ) . '</h4><ul class="tttc-assigned-players">';
		foreach ( $assignments as $assignment ) {
			$player_id = (int) $assignment->player_id;
			$rating    = null === $assignment->rating ? get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) : (int) $assignment->rating;
			$output   .= '<li>' . esc_html( get_the_title( $player_id ) ) . '<span>' . esc_html__( 'Rating:', 'table-tennis-tournament-for-clubs' ) . ' ' . esc_html( $rating ) . '</span></li>';
		}
		return $output . '</ul>';
	}
}
