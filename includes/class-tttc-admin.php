<?php
/**
 * WordPress admin screens and field handling.
 *
 * @package TableTennisTournamentForClubs
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class TTTC_Admin {
	private $score_error = '';
	private $score_form_scores = null;

	public function __construct() {
		add_action( 'admin_menu', array( $this, 'register_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_filter( 'wp_insert_post_data', array( $this, 'validate_player_duplicate' ), 10, 2 );
		add_action( 'save_post_' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'save_player' ) );
		add_action( 'save_post_' . TTTC_Plugin::TOURNAMENT_POST_TYPE, array( $this, 'save_tournament' ) );
		add_filter( 'manage_' . TTTC_Plugin::PLAYER_POST_TYPE . '_posts_columns', array( $this, 'player_columns' ) );
		add_action( 'manage_' . TTTC_Plugin::PLAYER_POST_TYPE . '_posts_custom_column', array( $this, 'player_column' ), 10, 2 );
		add_filter( 'manage_edit-' . TTTC_Plugin::PLAYER_POST_TYPE . '_sortable_columns', array( $this, 'player_sortable_columns' ) );
		add_action( 'restrict_manage_posts', array( $this, 'player_filters' ) );
		add_action( 'pre_get_posts', array( $this, 'filter_players' ) );
		add_filter( 'manage_' . TTTC_Plugin::TOURNAMENT_POST_TYPE . '_posts_columns', array( $this, 'tournament_columns' ) );
		add_action( 'manage_' . TTTC_Plugin::TOURNAMENT_POST_TYPE . '_posts_custom_column', array( $this, 'tournament_column' ), 10, 2 );
		add_action( 'admin_post_tttc_update_players', array( $this, 'update_players' ) );
		add_action( 'admin_post_tttc_reorder_players', array( $this, 'reorder_players' ) );
		add_action( 'admin_post_tttc_save_scores', array( $this, 'save_scores' ) );
		add_action( 'admin_post_tttc_merge_players', array( $this, 'merge_players' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'redirect_post_location', array( $this, 'redirect_after_post_save' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'player_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'handle_player_merge_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'merge_admin_notices' ) );
	}

	public function register_menu() {
		add_options_page( __( 'Table Tennis Settings', 'table-tennis-tournament-for-clubs' ), __( 'Table Tennis', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-settings', array( $this, 'settings_page' ) );
		add_menu_page(
			__( 'Table Tennis Clubs', 'table-tennis-tournament-for-clubs' ),
			__( 'Table Tennis', 'table-tennis-tournament-for-clubs' ),
			'edit_posts',
			'tttc-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-awards'
		);
		add_submenu_page( 'tttc-dashboard', __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-dashboard', array( $this, 'dashboard' ) );
		add_submenu_page( 'tttc-dashboard', __( 'Players', 'table-tennis-tournament-for-clubs' ), __( 'Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE );
		add_submenu_page( 'tttc-dashboard', __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::TOURNAMENT_POST_TYPE );
		add_submenu_page( null, __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-assignments', array( $this, 'assignments_page' ) );
		add_submenu_page( null, __( 'Order Players', 'table-tennis-tournament-for-clubs' ), __( 'Order Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-order', array( $this, 'order_page' ) );
		add_submenu_page( null, __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-scores', array( $this, 'scores_page' ) );
		add_submenu_page( null, __( 'Merge Players', 'table-tennis-tournament-for-clubs' ), __( 'Merge Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-merge-players', array( $this, 'merge_page' ) );
	}

	public function register_settings() {
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_FROM, array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_SUBJECT, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_BODY, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );

		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_DEFAULT_GAMES, array( 'sanitize_callback' => array( $this, 'sanitize_games_setting' ) ) );
		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_DEFAULT_TYPE, array( 'sanitize_callback' => array( $this, 'sanitize_type_setting' ) ) );
		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_FORMAT_RANGES, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_format_ranges' ) ) );

		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_FROM, array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_SUBJECT, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_BODY, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_DEFAULT_GAMES, array( 'sanitize_callback' => array( $this, 'sanitize_games_setting' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_DEFAULT_TYPE, array( 'sanitize_callback' => array( $this, 'sanitize_type_setting' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_FORMAT_RANGES, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_format_ranges' ) ) );
	}

	public function sanitize_games_setting( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( '3', '5' ), true ) ? $value : '3';
	}

	public function sanitize_type_setting( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'senior', 'youth', 'both' ), true ) ? $value : 'both';
	}

	public function settings_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'email';
		if ( ! in_array( $active_tab, array( 'email', 'formats' ), true ) ) {
			$active_tab = 'email';
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Table Tennis Settings', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<nav class="nav-tab-wrapper">
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=email' ) ); ?>" class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></a>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=formats' ) ); ?>" class="nav-tab <?php echo 'formats' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tournament Formats', 'table-tennis-tournament-for-clubs' ); ?></a>
			</nav>

			<?php if ( 'email' === $active_tab ) :
				$from    = get_option( TTTC_Plugin::OPTION_EMAIL_FROM, get_option( 'admin_email' ) );
				$subject = get_option( TTTC_Plugin::OPTION_EMAIL_SUBJECT, __( 'Signup confirmed for [tournament-name]', 'table-tennis-tournament-for-clubs' ) );
				$body    = get_option( TTTC_Plugin::OPTION_EMAIL_BODY, __( "Hello,\n\nYour signup for [tournament-name] on [tournament-date] has been received.\n\nView the tournament: [tournament-link]\n\nWe look forward to seeing you.", 'table-tennis-tournament-for-clubs' ) );
				?>
				<p><?php esc_html_e( 'Configure the confirmation email sent after a player signs up for an Upcoming tournament.', 'table-tennis-tournament-for-clubs' ); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields( 'tttc_email_settings' ); ?>
					<table class="form-table" role="presentation">
						<tr><th scope="row"><label for="tttc-email-from"><?php esc_html_e( 'Send from email address', 'table-tennis-tournament-for-clubs' ); ?></label></th><td><input type="email" class="regular-text" id="tttc-email-from" name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_FROM ); ?>" value="<?php echo esc_attr( $from ); ?>" required><p class="description"><?php esc_html_e( 'This address is used in the From header.', 'table-tennis-tournament-for-clubs' ); ?></p></td></tr>
						<tr><th scope="row"><label for="tttc-email-subject"><?php esc_html_e( 'Subject', 'table-tennis-tournament-for-clubs' ); ?></label></th><td><input type="text" class="large-text" id="tttc-email-subject" name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_SUBJECT ); ?>" value="<?php echo esc_attr( $subject ); ?>" required><p class="description"><?php esc_html_e( 'Available merge fields: [tournament-name], [tournament-date], and [tournament-link].', 'table-tennis-tournament-for-clubs' ); ?></p></td></tr>
						<tr><th scope="row"><label for="tttc-email-body"><?php esc_html_e( 'Body', 'table-tennis-tournament-for-clubs' ); ?></label></th><td><textarea class="large-text" rows="10" id="tttc-email-body" name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_BODY ); ?>" required><?php echo esc_textarea( $body ); ?></textarea><p class="description"><?php esc_html_e( 'Available merge fields: [tournament-name], [tournament-date], and [tournament-link].', 'table-tennis-tournament-for-clubs' ); ?></p></td></tr>
					</table>
					<?php submit_button(); ?>
				</form>
			<?php else :
				$default_games = get_option( TTTC_Plugin::OPTION_DEFAULT_GAMES, '3' );
				$default_type  = get_option( TTTC_Plugin::OPTION_DEFAULT_TYPE, 'both' );
				$format_ranges = TTTC_Plugin::get_format_ranges();
				?>
				<p><?php esc_html_e( 'Configure default tournament options and player count brackets for group distribution.', 'table-tennis-tournament-for-clubs' ); ?></p>
				<form method="post" action="options.php">
					<?php settings_fields( 'tttc_format_settings' ); ?>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><label for="tttc-default-games"><?php esc_html_e( 'Default Best of', 'table-tennis-tournament-for-clubs' ); ?></label></th>
							<td>
								<select id="tttc-default-games" name="<?php echo esc_attr( TTTC_Plugin::OPTION_DEFAULT_GAMES ); ?>">
									<option value="3" <?php selected( $default_games, '3' ); ?>>3</option>
									<option value="5" <?php selected( $default_games, '5' ); ?>>5</option>
								</select>
								<p class="description"><?php esc_html_e( 'Default match format for newly created tournaments.', 'table-tennis-tournament-for-clubs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><label for="tttc-default-type"><?php esc_html_e( 'Default player type', 'table-tennis-tournament-for-clubs' ); ?></label></th>
							<td>
								<select id="tttc-default-type" name="<?php echo esc_attr( TTTC_Plugin::OPTION_DEFAULT_TYPE ); ?>">
									<option value="senior" <?php selected( $default_type, 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
									<option value="youth" <?php selected( $default_type, 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
									<option value="both" <?php selected( $default_type, 'both' ); ?>><?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></option>
								</select>
								<p class="description"><?php esc_html_e( 'Default player type restriction for newly created tournaments.', 'table-tennis-tournament-for-clubs' ); ?></p>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Group player brackets', 'table-tennis-tournament-for-clubs' ); ?></th>
							<td>
								<p class="description" style="margin-bottom: 12px;"><?php esc_html_e( 'Set the minimum and maximum active player thresholds for distributing players into 1 to 4 groups.', 'table-tennis-tournament-for-clubs' ); ?></p>
								<table class="widefat striped tttc-format-table" style="max-width: 600px;">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Groups', 'table-tennis-tournament-for-clubs' ); ?></th>
											<th><?php esc_html_e( 'Min players', 'table-tennis-tournament-for-clubs' ); ?></th>
											<th><?php esc_html_e( 'Max players', 'table-tennis-tournament-for-clubs' ); ?></th>
											<th><?php esc_html_e( 'Crossover format', 'table-tennis-tournament-for-clubs' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<?php
										$format_labels = array(
											1 => __( 'Single group round-robin', 'table-tennis-tournament-for-clubs' ),
											2 => __( 'Final and 3rd place match', 'table-tennis-tournament-for-clubs' ),
											3 => __( 'Winner round-robin', 'table-tennis-tournament-for-clubs' ),
											4 => __( 'Semi-finals, final, 3rd place', 'table-tennis-tournament-for-clubs' ),
										);
										for ( $g = 1; $g <= 4; $g++ ) :
											$min = isset( $format_ranges[ $g ]['min'] ) ? $format_ranges[ $g ]['min'] : 2;
											$max = isset( $format_ranges[ $g ]['max'] ) ? $format_ranges[ $g ]['max'] : 2;
											?>
											<tr>
												<td><strong><?php echo esc_html( sprintf( _n( '%d Group', '%d Groups', $g, 'table-tennis-tournament-for-clubs' ), $g ) ); ?></strong></td>
												<td><input type="number" class="small-text" min="2" step="1" name="<?php echo esc_attr( TTTC_Plugin::OPTION_FORMAT_RANGES . '[' . $g . '][min]' ); ?>" value="<?php echo esc_attr( $min ); ?>" required></td>
												<td><input type="number" class="small-text" min="2" step="1" name="<?php echo esc_attr( TTTC_Plugin::OPTION_FORMAT_RANGES . '[' . $g . '][max]' ); ?>" value="<?php echo esc_attr( $max ); ?>" required></td>
												<td><span class="description"><?php echo esc_html( $format_labels[ $g ] ); ?></span></td>
											</tr>
										<?php endfor; ?>
									</tbody>
								</table>
							</td>
						</tr>
					</table>
					<?php submit_button(); ?>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$players = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
		$players = array_filter( $players, function ( $player ) {
			return '1' === get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true );
		} );
		$player_stats = array( 'male' => 0, 'female' => 0, 'senior' => 0, 'youth' => 0 );
		foreach ( $players as $player ) {
			$gender = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_GENDER, true );
			$type   = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
			$gender = in_array( $gender, array( 'male', 'female' ), true ) ? $gender : 'male';
			$type   = in_array( $type, array( 'senior', 'youth' ), true ) ? $type : 'senior';
			$player_stats[ $gender ]++;
			$player_stats[ $type ]++;
		}
		$top_players = $players;
		usort( $top_players, function ( $first, $second ) {
			$rating_difference = absint( get_post_meta( $second->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ) - absint( get_post_meta( $first->ID, TTTC_Plugin::PLAYER_META_RATING, true ) );
			if ( 0 !== $rating_difference ) {
				return $rating_difference;
			}

			$title_difference = strcasecmp( $first->post_title, $second->post_title );
			return 0 !== $title_difference ? $title_difference : $first->ID - $second->ID;
		} );
		$top_players = array_slice( $top_players, 0, 3 );

		$tournaments        = get_posts( array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1 ) );
		$tournament_types    = array( 'senior' => 0, 'youth' => 0, 'both' => 0 );
		$tournament_statuses = array_fill_keys( array_keys( TTTC_Plugin::statuses() ), 0 );
		$participant_total   = 0;
		$participating_count = 0;
		$tournament_wins     = array();
		foreach ( $tournaments as $tournament ) {
			$type   = get_post_meta( $tournament->ID, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
			$status = get_post_meta( $tournament->ID, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			$type   = in_array( $type, array( 'senior', 'youth', 'both' ), true ) ? $type : 'both';
			$status = array_key_exists( $status, $tournament_statuses ) ? $status : 'draft';
			$tournament_types[ $type ]++;
			$tournament_statuses[ $status ]++;
			$participant_count = count( $this->assigned_player_ids( $tournament->ID ) );
			if ( $participant_count ) {
				$participant_total += $participant_count;
				$participating_count++;
			}
			$winner_id = TTTC_Public::instance() ? TTTC_Public::instance()->tournament_winner_id( $tournament->ID ) : 0;
			if ( $winner_id ) {
				$tournament_wins[ $winner_id ] = isset( $tournament_wins[ $winner_id ] ) ? $tournament_wins[ $winner_id ] + 1 : 1;
			}
		}
		$average_participants = $participating_count ? round( $participant_total / $participating_count, 1 ) : 0;
		$player_count         = count( $players );
		$tournament_count     = count( $tournaments );
		$players_url          = admin_url( 'edit.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE );
		$tournaments_url      = admin_url( 'edit.php?post_type=' . TTTC_Plugin::TOURNAMENT_POST_TYPE );

		$top_winners = array();
		foreach ( $tournament_wins as $winner_id => $win_count ) {
			$winner_post = get_post( $winner_id );
			if ( $winner_post ) {
				$top_winners[] = array( 'player' => $winner_post, 'wins' => $win_count );
			}
		}
		usort( $top_winners, function ( $first, $second ) {
			$win_difference = $second['wins'] - $first['wins'];
			if ( 0 !== $win_difference ) {
				return $win_difference;
			}

			$title_difference = strcasecmp( $first['player']->post_title, $second['player']->post_title );
			return 0 !== $title_difference ? $title_difference : $first['player']->ID - $second['player']->ID;
		} );
		$top_winners = array_slice( $top_winners, 0, 3 );
		?>
		<div class="wrap tttc-dashboard">
			<h1><?php esc_html_e( 'Table Tennis Tournament for Clubs', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<p><?php esc_html_e( 'Manage your club players and tournaments from one place.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<div class="tttc-dashboard-grid">
				<div class="tttc-summary-card"><span class="dashicons dashicons-groups"></span><strong><?php echo esc_html( $player_count ); ?></strong><span><a href="<?php echo esc_url( $players_url ); ?>"><?php esc_html_e( 'Published players', 'table-tennis-tournament-for-clubs' ); ?></a></span></div>
				<div class="tttc-summary-card"><span class="dashicons dashicons-awards"></span><strong><?php echo esc_html( $tournament_count ); ?></strong><span><a href="<?php echo esc_url( $tournaments_url ); ?>"><?php esc_html_e( 'Published tournaments', 'table-tennis-tournament-for-clubs' ); ?></a></span></div>
			</div>
			<div class="tttc-dashboard-grid">
				<section class="tttc-dashboard-section">
					<h2><a href="<?php echo esc_url( $players_url ); ?>"><?php esc_html_e( 'Published players', 'table-tennis-tournament-for-clubs' ); ?></a></h2>
					<div class="tttc-stat-list">
						<div><span><?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['male'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['female'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['senior'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['youth'] ); ?></strong></div>
					</div>
					<h3><?php esc_html_e( 'Top 3 players by rating', 'table-tennis-tournament-for-clubs' ); ?></h3>
					<?php if ( empty( $top_players ) ) : ?>
						<p><?php esc_html_e( 'No active published players found.', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php else : ?>
						<ol class="tttc-top-players">
							<?php foreach ( $top_players as $player ) :
								$player_url = TTTC_Public::instance() ? TTTC_Public::instance()->player_url( $player->ID ) : '';
								?>
								<li><span><?php if ( $player_url ) : ?><a href="<?php echo esc_url( $player_url ); ?>"><?php echo esc_html( $player->post_title ); ?></a><?php else : ?><?php echo esc_html( $player->post_title ); ?><?php endif; ?></span><strong><?php echo esc_html( absint( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ) ); ?></strong></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
					<h3><?php esc_html_e( 'Top 3 players by tournament wins', 'table-tennis-tournament-for-clubs' ); ?></h3>
					<?php if ( empty( $top_winners ) ) : ?>
						<p><?php esc_html_e( 'No winners announced yet', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php else : ?>
						<ol class="tttc-top-players">
							<?php foreach ( $top_winners as $winner ) :
								$player_url = TTTC_Public::instance() ? TTTC_Public::instance()->player_url( $winner['player']->ID ) : '';
								?>
								<li><span><?php if ( $player_url ) : ?><a href="<?php echo esc_url( $player_url ); ?>"><?php echo esc_html( $winner['player']->post_title ); ?></a><?php else : ?><?php echo esc_html( $winner['player']->post_title ); ?><?php endif; ?></span><strong><?php echo esc_html( $winner['wins'] ); ?></strong></li>
							<?php endforeach; ?>
						</ol>
					<?php endif; ?>
				</section>
				<section class="tttc-dashboard-section">
					<h2><a href="<?php echo esc_url( $tournaments_url ); ?>"><?php esc_html_e( 'Published tournaments', 'table-tennis-tournament-for-clubs' ); ?></a></h2>
					<div class="tttc-stat-list">
						<div><span><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['senior'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['youth'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['both'] ); ?></strong></div>
						<div><span><?php esc_html_e( 'Average players per tournament', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $average_participants ); ?></strong></div>
					</div>
					<h3><?php esc_html_e( 'Tournament statuses', 'table-tennis-tournament-for-clubs' ); ?></h3>
					<div class="tttc-stat-list">
						<?php foreach ( TTTC_Plugin::statuses() as $status_key => $status_label ) : ?>
							<div><span><?php echo esc_html( $status_label ); ?></span><strong><?php echo esc_html( $tournament_statuses[ $status_key ] ); ?></strong></div>
						<?php endforeach; ?>
					</div>
				</section>
			</div>
		</div>
		<?php
	}

	public function register_meta_boxes() {
		add_meta_box( 'tttc-player-details', __( 'Player details', 'table-tennis-tournament-for-clubs' ), array( $this, 'player_meta_box' ), TTTC_Plugin::PLAYER_POST_TYPE, 'normal', 'high' );
		add_meta_box( 'tttc-tournament-details', __( 'Tournament details', 'table-tennis-tournament-for-clubs' ), array( $this, 'tournament_meta_box' ), TTTC_Plugin::TOURNAMENT_POST_TYPE, 'normal', 'high' );
	}

	public function player_meta_box( $post ) {
		wp_nonce_field( 'tttc_save_player', 'tttc_player_nonce' );
		$rating = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_RATING, true );
		$email  = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_EMAIL, true );
		$active = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true );
		$gender = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_GENDER, true );
		$type   = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
		$gender = $gender ? $gender : 'male';
		$type   = $type ? $type : 'senior';
		$active = '' === $active ? '1' : $active;
		?>
		<p><label for="tttc-rating"><strong><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input class="small-text" type="number" min="0" step="1" id="tttc-rating" name="tttc_rating" value="<?php echo esc_attr( $rating ); ?>" required></p>
		<p><label for="tttc-email"><strong><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input class="regular-text" type="email" id="tttc-email" name="tttc_email" value="<?php echo esc_attr( $email ); ?>"></p>
		<p><label for="tttc-gender"><strong><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-gender" name="tttc_gender"><option value="male" <?php selected( $gender, 'male' ); ?>><?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></option><option value="female" <?php selected( $gender, 'female' ); ?>><?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
		<p><label for="tttc-type"><strong><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-type" name="tttc_type"><option value="senior" <?php selected( $type, 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option><option value="youth" <?php selected( $type, 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
		<p><label><input type="checkbox" name="tttc_active" value="1" <?php checked( '1', $active ); ?>> <?php esc_html_e( 'Player is active and available for new tournament assignments', 'table-tennis-tournament-for-clubs' ); ?></label></p>
		<?php
	}

	public function tournament_meta_box( $post ) {
		wp_nonce_field( 'tttc_save_tournament', 'tttc_tournament_nonce' );
		$date   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$games  = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$status = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
		$type   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
		$status = $status ? $status : 'draft';
		if ( '' === $type ) {
			$type = get_option( TTTC_Plugin::OPTION_DEFAULT_TYPE, 'both' );
		}
		$type = in_array( $type, array( 'senior', 'youth', 'both' ), true ) ? $type : 'both';
		if ( '' === $games ) {
			$games = get_option( TTTC_Plugin::OPTION_DEFAULT_GAMES, '3' );
		}
		$games = in_array( (string) $games, array( '3', '5' ), true ) ? (string) $games : '3';

		$format_ranges = TTTC_Plugin::get_format_ranges( $post->ID );
		$has_scores    = TTTC_Plugin::has_scores( $post->ID );
		?>
		<?php if ( $has_scores ) : ?>
			<div class="notice notice-warning inline" style="margin: 0 0 16px;">
				<p><strong><?php esc_html_e( 'Note:', 'table-tennis-tournament-for-clubs' ); ?></strong> <?php esc_html_e( 'Match scores have already been recorded for this tournament. Format settings (Best of and group player brackets) cannot be changed.', 'table-tennis-tournament-for-clubs' ); ?></p>
			</div>
		<?php endif; ?>
		<p><label for="tttc-date"><strong><?php esc_html_e( 'Date', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input type="date" id="tttc-date" name="tttc_date" value="<?php echo esc_attr( $date ); ?>" required></p>
		<p><label for="tttc-games"><strong><?php esc_html_e( 'Best of', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-games" name="tttc_games" required <?php disabled( $has_scores ); ?>><option value="3" <?php selected( $games, '3' ); ?>>3</option><option value="5" <?php selected( $games, '5' ); ?>>5</option></select></p>
		<p><label for="tttc-type"><strong><?php esc_html_e( 'Player type', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-type" name="tttc_type"><option value="senior" <?php selected( $type, 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option><option value="youth" <?php selected( $type, 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option><option value="both" <?php selected( $type, 'both' ); ?>><?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
		<p><label for="tttc-status"><strong><?php esc_html_e( 'Status', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-status" name="tttc_status">
			<?php foreach ( TTTC_Plugin::statuses() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>

		<div class="tttc-tournament-format-section" style="margin-top: 16px; border-top: 1px solid #dcdcde; padding-top: 12px;">
			<p><strong><?php esc_html_e( 'Group player brackets (format)', 'table-tennis-tournament-for-clubs' ); ?></strong></p>
			<p class="description"><?php esc_html_e( 'Customize player count brackets for this tournament or leave default.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<table class="widefat striped tttc-format-table" style="max-width: 500px; margin-top: 8px;">
				<thead>
					<tr>
						<th><?php esc_html_e( 'Groups', 'table-tennis-tournament-for-clubs' ); ?></th>
						<th><?php esc_html_e( 'Min', 'table-tennis-tournament-for-clubs' ); ?></th>
						<th><?php esc_html_e( 'Max', 'table-tennis-tournament-for-clubs' ); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php for ( $g = 1; $g <= 4; $g++ ) :
						$min = isset( $format_ranges[ $g ]['min'] ) ? $format_ranges[ $g ]['min'] : 2;
						$max = isset( $format_ranges[ $g ]['max'] ) ? $format_ranges[ $g ]['max'] : 2;
						?>
						<tr>
							<td><strong><?php echo esc_html( sprintf( _n( '%d Group', '%d Groups', $g, 'table-tennis-tournament-for-clubs' ), $g ) ); ?></strong></td>
							<td><input type="number" class="small-text" min="2" step="1" name="tttc_format_ranges[<?php echo esc_attr( $g ); ?>][min]" value="<?php echo esc_attr( $min ); ?>" required <?php disabled( $has_scores ); ?>></td>
							<td><input type="number" class="small-text" min="2" step="1" name="tttc_format_ranges[<?php echo esc_attr( $g ); ?>][max]" value="<?php echo esc_attr( $max ); ?>" required <?php disabled( $has_scores ); ?>></td>
						</tr>
					<?php endfor; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function validate_player_duplicate( $data, $postarr ) {
		if ( TTTC_Plugin::PLAYER_POST_TYPE !== $data['post_type'] ) {
			return $data;
		}

		if ( in_array( $data['post_status'], array( 'auto-draft', 'trash' ), true ) ) {
			return $data;
		}

		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( isset( $postarr['ID'] ) ? $postarr['ID'] : 0 ) ) {
			return $data;
		}

		if ( ! isset( $_POST['tttc_email'] ) ) {
			return $data;
		}

		$name  = preg_replace( '/\s+/', ' ', trim( $data['post_title'] ) );
		$email = strtolower( sanitize_email( wp_unslash( $_POST['tttc_email'] ) ) );

		if ( '' === $name || '' === $email ) {
			return $data;
		}

		$current_id = isset( $postarr['ID'] ) ? absint( $postarr['ID'] ) : 0;

		$existing_players = get_posts( array(
			'post_type'      => TTTC_Plugin::PLAYER_POST_TYPE,
			'post_status'    => array( 'publish', 'draft', 'future', 'pending', 'private' ),
			'posts_per_page' => -1,
			'post__not_in'   => $current_id ? array( $current_id ) : array(),
			'meta_query'     => array(
				array(
					'key'     => TTTC_Plugin::PLAYER_META_EMAIL,
					'value'   => $email,
					'compare' => '=',
				),
			),
		) );

		foreach ( $existing_players as $player ) {
			$existing_name = preg_replace( '/\s+/', ' ', trim( $player->post_title ) );
			if ( 0 === strcasecmp( $existing_name, $name ) ) {
				wp_die(
					esc_html__( 'A player with this name and email already exists.', 'table-tennis-tournament-for-clubs' ),
					esc_html__( 'Duplicate Player Error', 'table-tennis-tournament-for-clubs' ),
					array( 'back_link' => true )
				);
			}
		}

		return $data;
	}

	public function save_player( $post_id ) {
		if ( ! $this->can_save( $post_id, 'tttc_player_nonce', 'tttc_save_player' ) ) {
			return;
		}
		$rating = isset( $_POST['tttc_rating'] ) ? max( 0, absint( $_POST['tttc_rating'] ) ) : 0;
		$email  = isset( $_POST['tttc_email'] ) ? sanitize_email( wp_unslash( $_POST['tttc_email'] ) ) : '';
		$gender = isset( $_POST['tttc_gender'] ) ? sanitize_key( wp_unslash( $_POST['tttc_gender'] ) ) : 'male';
		$type   = isset( $_POST['tttc_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_type'] ) ) : 'senior';
		$gender = in_array( $gender, array( 'male', 'female' ), true ) ? $gender : 'male';
		$type   = in_array( $type, array( 'senior', 'youth' ), true ) ? $type : 'senior';
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_RATING, $rating );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_EMAIL, $email );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_GENDER, $gender );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_TYPE, $type );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_ACTIVE, isset( $_POST['tttc_active'] ) ? '1' : '0' );
	}

	public function save_tournament( $post_id ) {
		if ( ! $this->can_save( $post_id, 'tttc_tournament_nonce', 'tttc_save_tournament' ) ) {
			return;
		}
		$date   = isset( $_POST['tttc_date'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_date'] ) ) : '';
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date ) {
			$date = '';
		}
		$type   = isset( $_POST['tttc_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_type'] ) ) : 'both';
		$status = isset( $_POST['tttc_status'] ) ? sanitize_key( $_POST['tttc_status'] ) : 'draft';
		if ( ! in_array( $type, array( 'senior', 'youth', 'both' ), true ) ) {
			$type = 'both';
		}
		if ( ! array_key_exists( $status, TTTC_Plugin::statuses() ) ) {
			$status = 'draft';
		}
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, $date );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TYPE, $type );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, $status );

		$has_scores = TTTC_Plugin::has_scores( $post_id );
		if ( ! $has_scores ) {
			$games = isset( $_POST['tttc_games'] ) ? sanitize_key( wp_unslash( $_POST['tttc_games'] ) ) : '3';
			if ( ! in_array( $games, array( '3', '5' ), true ) ) {
				$games = '3';
			}
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, $games );

			if ( isset( $_POST['tttc_format_ranges'] ) && is_array( $_POST['tttc_format_ranges'] ) ) {
				$ranges = TTTC_Plugin::sanitize_format_ranges( wp_unslash( $_POST['tttc_format_ranges'] ) );
				update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_FORMAT_RANGES, $ranges );
			}
		}
	}

	public function redirect_after_post_save( $location, $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( TTTC_Plugin::PLAYER_POST_TYPE, TTTC_Plugin::TOURNAMENT_POST_TYPE ), true ) ) {
			return $location;
		}

		return admin_url( 'edit.php?post_type=' . $post_type );
	}

	private function can_save( $post_id, $nonce_name, $nonce_action ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
			return false;
		}
		if ( ! isset( $_POST[ $nonce_name ] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST[ $nonce_name ] ) ), $nonce_action ) ) {
			return false;
		}
		return current_user_can( 'edit_post', $post_id );
	}

	public function player_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_gender' => __( 'Gender', 'table-tennis-tournament-for-clubs' ), 'tttc_type' => __( 'Type', 'table-tennis-tournament-for-clubs' ), 'tttc_rating' => __( 'Rating', 'table-tennis-tournament-for-clubs' ), 'tttc_email' => __( 'Email', 'table-tennis-tournament-for-clubs' ), 'tttc_active' => __( 'Active', 'table-tennis-tournament-for-clubs' ), 'tttc_url' => __( 'Url', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
	}

	public function player_sortable_columns( $columns ) {
		$columns['tttc_gender'] = 'tttc_gender';
		$columns['tttc_type']   = 'tttc_type';
		$columns['tttc_rating'] = 'tttc_rating';
		$columns['tttc_email']  = 'tttc_email';
		$columns['tttc_active'] = 'tttc_active';

		return $columns;
	}

	public function player_column( $column, $post_id ) {
		if ( 'tttc_rating' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_RATING, true ) );
		} elseif ( 'tttc_gender' === $column ) {
			$gender = get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_GENDER, true );
			echo esc_html( 'female' === $gender ? __( 'Female', 'table-tennis-tournament-for-clubs' ) : __( 'Male', 'table-tennis-tournament-for-clubs' ) );
		} elseif ( 'tttc_type' === $column ) {
			$type = get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_TYPE, true );
			echo esc_html( 'youth' === $type ? __( 'Youth', 'table-tennis-tournament-for-clubs' ) : __( 'Senior', 'table-tennis-tournament-for-clubs' ) );
		} elseif ( 'tttc_email' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_EMAIL, true ) );
		} elseif ( 'tttc_active' === $column ) {
			echo '1' === get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ? esc_html__( 'Yes', 'table-tennis-tournament-for-clubs' ) : esc_html__( 'No', 'table-tennis-tournament-for-clubs' );
		} elseif ( 'tttc_url' === $column ) {
			$player_url = TTTC_Public::instance()->player_url( $post_id );
			if ( $player_url ) {
				echo '<a class="button-link" href="' . esc_url( $player_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'table-tennis-tournament-for-clubs' ) . '</a>';
			}
		}
	}

	public function player_filters( $post_type ) {
		if ( TTTC_Plugin::PLAYER_POST_TYPE !== $post_type ) {
			return;
		}

		$gender = isset( $_GET['tttc_gender'] ) ? sanitize_key( wp_unslash( $_GET['tttc_gender'] ) ) : '';
		$type   = isset( $_GET['tttc_type'] ) ? sanitize_key( wp_unslash( $_GET['tttc_type'] ) ) : '';
		?>
		<select name="tttc_gender">
			<option value=""><?php esc_html_e( 'All genders', 'table-tennis-tournament-for-clubs' ); ?></option>
			<option value="male" <?php selected( $gender, 'male' ); ?>><?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></option>
			<option value="female" <?php selected( $gender, 'female' ); ?>><?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></option>
		</select>
		<select name="tttc_type">
			<option value=""><?php esc_html_e( 'All types', 'table-tennis-tournament-for-clubs' ); ?></option>
			<option value="senior" <?php selected( $type, 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
			<option value="youth" <?php selected( $type, 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
		</select>
		<?php
	}

	public function filter_players( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || TTTC_Plugin::PLAYER_POST_TYPE !== $query->get( 'post_type' ) ) {
			return;
		}

		$meta_query = (array) $query->get( 'meta_query' );
		$filters    = array(
			array( 'key' => TTTC_Plugin::PLAYER_META_GENDER, 'query_var' => 'tttc_gender', 'values' => array( 'male', 'female' ) ),
			array( 'key' => TTTC_Plugin::PLAYER_META_TYPE, 'query_var' => 'tttc_type', 'values' => array( 'senior', 'youth' ) ),
		);

		foreach ( $filters as $filter ) {
			$value = isset( $_GET[ $filter['query_var'] ] ) ? sanitize_key( wp_unslash( $_GET[ $filter['query_var'] ] ) ) : '';
			if ( in_array( $value, $filter['values'], true ) ) {
				$meta_query[] = array( 'key' => $filter['key'], 'value' => $value );
			}
		}

		if ( count( $meta_query ) > 0 ) {
			$query->set( 'meta_query', $meta_query );
		}

		$sortable_meta_keys = array(
			'tttc_gender' => array( 'key' => TTTC_Plugin::PLAYER_META_GENDER, 'orderby' => 'meta_value' ),
			'tttc_type'   => array( 'key' => TTTC_Plugin::PLAYER_META_TYPE, 'orderby' => 'meta_value' ),
			'tttc_rating' => array( 'key' => TTTC_Plugin::PLAYER_META_RATING, 'orderby' => 'meta_value_num' ),
			'tttc_email'  => array( 'key' => TTTC_Plugin::PLAYER_META_EMAIL, 'orderby' => 'meta_value' ),
			'tttc_active' => array( 'key' => TTTC_Plugin::PLAYER_META_ACTIVE, 'orderby' => 'meta_value' ),
		);
		$orderby = $query->get( 'orderby' );
		if ( isset( $sortable_meta_keys[ $orderby ] ) ) {
			$query->set( 'meta_key', $sortable_meta_keys[ $orderby ]['key'] );
			$query->set( 'orderby', $sortable_meta_keys[ $orderby ]['orderby'] );
		}
	}

	public function tournament_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_date' => __( 'Date', 'table-tennis-tournament-for-clubs' ), 'tttc_games' => __( 'Best of', 'table-tennis-tournament-for-clubs' ), 'tttc_status' => __( 'Status', 'table-tennis-tournament-for-clubs' ), 'tttc_players' => __( 'Players', 'table-tennis-tournament-for-clubs' ), 'tttc_url' => __( 'Url', 'table-tennis-tournament-for-clubs' ), 'tttc_scores' => __( 'Scores', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
	}

	public function tournament_column( $column, $post_id ) {
		if ( 'tttc_date' === $column ) {
			$date        = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
			$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
			echo esc_html( $date_object ? $date_object->format( 'd-m-Y' ) : $date );
		} elseif ( 'tttc_games' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true ) );
		} elseif ( 'tttc_status' === $column ) {
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			echo esc_html( isset( TTTC_Plugin::statuses()[ $status ] ) ? TTTC_Plugin::statuses()[ $status ] : $status );
		} elseif ( 'tttc_players' === $column ) {
			$count = $this->assigned_player_ids( $post_id );
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			echo esc_html( count( $count ) );
			if ( ! in_array( $status, array( 'active', 'completed' ), true ) ) {
				echo ' <a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-assignments&tournament_id=' . $post_id ) ) . '">' . esc_html__( 'Manage', 'table-tennis-tournament-for-clubs' ) . '</a>';
				echo ' <a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-order&tournament_id=' . $post_id ) ) . '">' . esc_html__( 'Order', 'table-tennis-tournament-for-clubs' ) . '</a>';
			}
		} elseif ( 'tttc_url' === $column ) {
			$website_url = $this->tournament_url( $post_id );
			if ( $website_url ) {
				echo '<a class="button-link" href="' . esc_url( $website_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'table-tennis-tournament-for-clubs' ) . '</a>';
			}
		} elseif ( 'tttc_scores' === $column ) {
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			if ( ! in_array( $status, array( 'draft', 'completed' ), true ) ) {
				echo '<a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $post_id ) ) . '">' . esc_html__( 'Enter scores', 'table-tennis-tournament-for-clubs' ) . '</a>';
			}
		}
	}

	private function tournament_url( $post_id ) {
		$date        = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );

		return $date_object ? home_url( user_trailingslashit( 'toernooi/' . get_post_field( 'post_name', $post_id ) . '/' . $date_object->format( 'd-m-Y' ) ) ) : '';
	}

	public function scores_page( $submitted_tournament_id = 0 ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$tournament_id = $submitted_tournament_id ? absint( $submitted_tournament_id ) : ( isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0 );
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		$games         = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$games         = in_array( (string) $games, array( '3', '5' ), true ) ? (int) $games : 3;
		$schedule      = TTTC_Public::instance()->tournament_schedule( $tournament_id );
		$format_ranges = TTTC_Plugin::get_format_ranges( $tournament_id );
		$min_players   = isset( $format_ranges[1]['min'] ) ? $format_ranges[1]['min'] : 4;
		$max_players   = isset( $format_ranges[4]['max'] ) ? $format_ranges[4]['max'] : 28;
		$error_data    = get_transient( $this->score_error_transient_key( $tournament_id ) );
		if ( is_array( $error_data ) ) {
			delete_transient( $this->score_error_transient_key( $tournament_id ) );
			$this->score_error       = isset( $error_data['message'] ) ? $error_data['message'] : '';
			$this->score_form_scores = isset( $error_data['scores'] ) ? $error_data['scores'] : null;
		}
		$saved_scores = null !== $this->score_form_scores ? $this->score_form_scores : $this->saved_scores( $tournament_id );
		$competition  = TTTC_Competition::calculate( $schedule, $saved_scores, $games );
		$ordered_stages = array();
		foreach ( $competition['stages'] as $stage ) {
			if ( 'final' !== $stage['id'] ) {
				$ordered_stages[] = $stage;
			}
		}
		foreach ( $competition['stages'] as $stage ) {
			if ( 'final' === $stage['id'] ) {
				$ordered_stages[] = $stage;
			}
		}
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_the_title( $tournament_id ) . ' - ' . __( 'Scores', 'table-tennis-tournament-for-clubs' ) ); ?></h1>
			<?php if ( $this->score_error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $this->score_error ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tournament scores updated.', 'table-tennis-tournament-for-clubs' ); ?></p></div><?php endif; ?>
			<?php if ( empty( $schedule ) ) : ?>
				<p><?php echo esc_html( sprintf( __( 'A score sheet is available when the tournament has %1$d to %2$d assigned active players.', 'table-tennis-tournament-for-clubs' ), $min_players, $max_players ) ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-scores-form">
					<input type="hidden" name="action" value="tttc_save_scores"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
					<?php wp_nonce_field( 'tttc_save_scores', 'tttc_scores_nonce' ); ?>
					<div class="tttc-scores-toolbar">
						<button type="button" class="button tttc-print-button" onclick="window.print(); return false;"><?php esc_html_e( 'Print current group', 'table-tennis-tournament-for-clubs' ); ?></button>
					</div>
					<div class="tttc-public-group-tabs tttc-admin-group-tabs" role="tablist" aria-label="<?php esc_attr_e( 'Groups', 'table-tennis-tournament-for-clubs' ); ?>">
						<?php foreach ( $schedule as $group_index => $group_schedule ) : $tab_id = 'tttc-score-group-tab-' . ( $group_index + 1 ); $panel_id = 'tttc-score-group-panel-' . ( $group_index + 1 ); ?>
							<button id="<?php echo esc_attr( $tab_id ); ?>" class="tttc-admin-group-tab<?php echo 0 === $group_index ? ' is-active' : ''; ?>" type="button" role="tab" aria-controls="<?php echo esc_attr( $panel_id ); ?>" aria-selected="<?php echo 0 === $group_index ? 'true' : 'false'; ?>" tabindex="<?php echo 0 === $group_index ? '0' : '-1'; ?>"><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $group_index + 1 ) ); ?></button>
						<?php endforeach; ?>
					</div>
					<?php foreach ( $schedule as $group_index => $group_schedule ) : ?>
						<section id="<?php echo esc_attr( 'tttc-score-group-panel-' . ( $group_index + 1 ) ); ?>" class="tttc-public-group tttc-admin-group<?php echo 0 === $group_index ? ' is-active' : ''; ?>" role="tabpanel" aria-labelledby="<?php echo esc_attr( 'tttc-score-group-tab-' . ( $group_index + 1 ) ); ?>"<?php echo 0 === $group_index ? '' : ' hidden'; ?>>
						<h2><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $group_index + 1 ) ); ?></h2>
						<div class="tttc-public-round-grid">
						<?php foreach ( $group_schedule['rounds'] as $round_number => $round ) : ?>
							<div class="tttc-public-round">
								<h3 class="tttc-public-round-title"><?php echo esc_html( sprintf( __( 'Round %d', 'table-tennis-tournament-for-clubs' ), $round_number + 1 ) ); ?></h3>
								<div class="tttc-scores-table-wrap"><table class="widefat striped tttc-scores-table"><thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?></tr></thead><tbody>
								<?php foreach ( $round as $match_number => $match ) : $match_key = $this->match_key( $match[0]->ID, $match[1]->ID ); ?>
									<tr><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $match[0]->post_title ); ?></td><td><?php echo esc_html( $match[1]->post_title ); ?></td><?php for ( $game = 0; $game < $games; $game++ ) : ?><td><span class="tttc-score-pair"><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][0]" value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][0] ) ? $saved_scores[ $match_key ][ $game ][0] : '' ); ?>"><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][1]" value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][1] ) ? $saved_scores[ $match_key ][ $game ][1] : '' ); ?>"></span></td><?php endfor; ?></tr>
								<?php endforeach; ?></tbody></table></div>
							</div>
						<?php endforeach; ?>
						</div>
						</section>
					<?php endforeach; ?>
					<?php foreach ( $ordered_stages as $stage ) : ?>
						<section class="tttc-crossover-stage">
							<h2><?php echo esc_html( __( $stage['label'], 'table-tennis-tournament-for-clubs' ) ); ?></h2>
							<div class="tttc-scores-table-wrap"><table class="widefat striped tttc-scores-table"><thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?></tr></thead><tbody>
							<?php foreach ( $stage['matches'] as $match_number => $match ) : $available = $match['players'][0] && $match['players'][1]; $score_key = $match['score_key']; ?>
								<tr><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $available ? $match['players'][0]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td><td><?php echo esc_html( $available ? $match['players'][1]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td><?php for ( $game = 0; $game < $games; $game++ ) : ?><td><span class="tttc-score-pair"><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][0]" value="<?php echo esc_attr( isset( $saved_scores[ $score_key ][ $game ][0] ) ? $saved_scores[ $score_key ][ $game ][0] : '' ); ?>"<?php disabled( ! $available ); ?>><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][1]" value="<?php echo esc_attr( isset( $saved_scores[ $score_key ][ $game ][1] ) ? $saved_scores[ $score_key ][ $game ][1] : '' ); ?>"<?php disabled( ! $available ); ?>></span></td><?php endfor; ?></tr>
							<?php endforeach; ?></tbody></table></div>
						</section>
					<?php endforeach; ?>
					<p><button class="button button-primary"><?php esc_html_e( 'Save scores', 'table-tennis-tournament-for-clubs' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save_scores() {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_scores_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_scores_nonce'] ) ), 'tttc_save_scores' ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}

		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		$games         = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$games         = in_array( (string) $games, array( '3', '5' ), true ) ? (int) $games : 3;
		$schedule      = TTTC_Public::instance()->tournament_schedule( $tournament_id );
		$submitted     = isset( $_POST['scores'] ) && is_array( $_POST['scores'] ) ? wp_unslash( $_POST['scores'] ) : array();
		$validated     = array();

		foreach ( $schedule as $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round_number => $round ) {
				foreach ( $round as $match_number => $match ) {
					$match_key    = $this->match_key( $match[0]->ID, $match[1]->ID );
					$match_scores = isset( $submitted[ $match_key ] ) && is_array( $submitted[ $match_key ] ) ? $submitted[ $match_key ] : array();
					$match_result = $this->validate_match_scores( $match_scores, $games );

					if ( is_wp_error( $match_result ) ) {
						set_transient(
							$this->score_error_transient_key( $tournament_id ),
							array(
								'message' => $match_result->get_error_message(),
								'scores'  => $this->form_scores( $submitted, $schedule, $games ),
							),
							MINUTE_IN_SECONDS
						);
						wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&error=1' ) );
						exit;
					}

					$validated[ $match_key ] = $match_result;
				}
			}
		}
		$competition = TTTC_Competition::calculate( $schedule, $validated, $games );
		foreach ( $competition['stages'] as $stage ) {
			foreach ( $stage['matches'] as $match ) {
				if ( ! $match['players'][0] || ! $match['players'][1] ) {
					continue;
				}
				$match_scores = isset( $submitted[ $match['score_key'] ] ) && is_array( $submitted[ $match['score_key'] ] ) ? $submitted[ $match['score_key'] ] : array();
				$match_result = $this->validate_match_scores( $match_scores, $games );
				if ( is_wp_error( $match_result ) ) {
					set_transient( $this->score_error_transient_key( $tournament_id ), array( 'message' => $match_result->get_error_message(), 'scores' => $this->form_scores( $submitted, $schedule, $games ) ), MINUTE_IN_SECONDS );
					wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&error=1' ) );
					exit;
				}
				$validated[ $match['score_key'] ] = $match_result;
			}
		}

		global $wpdb;
		$table = TTTC_Plugin::scores_table_name();
		$wpdb->delete( $table, array( 'tournament_id' => $tournament_id ), array( '%d' ) );

		foreach ( $schedule as $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round_number => $round ) {
				foreach ( $round as $match_number => $match ) {
					$match_key    = $this->match_key( $match[0]->ID, $match[1]->ID );
					$scores       = $validated[ $match_key ];
					$wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'match_key' => $match_key, 'player_one_id' => min( $match[0]->ID, $match[1]->ID ), 'player_two_id' => max( $match[0]->ID, $match[1]->ID ), 'round_number' => $round_number + 1, 'match_number' => $match_number + 1, 'scores' => wp_json_encode( $scores ), 'updated_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ) );
				}
			}
		}
		foreach ( $competition['stages'] as $stage ) {
			foreach ( $stage['matches'] as $match_number => $match ) {
				if ( ! $match['players'][0] || ! $match['players'][1] ) {
					continue;
				}
				$wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'match_key' => $match['score_key'], 'player_one_id' => min( $match['players'][0]->ID, $match['players'][1]->ID ), 'player_two_id' => max( $match['players'][0]->ID, $match['players'][1]->ID ), 'round_number' => 0, 'match_number' => $match_number + 1, 'scores' => wp_json_encode( $validated[ $match['score_key'] ] ), 'updated_at' => current_time( 'mysql', true ) ), array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&updated=1' ) );
		exit;
	}

	private function validate_match_scores( $match_scores, $games ) {
		$validated = array();
		$wins      = array( 0, 0 );
		$has_score = false;
		$required  = (int) ceil( $games / 2 );
		$has_blank = false;

		for ( $game = 0; $game < $games; $game++ ) {
			$game_score = isset( $match_scores[ $game ] ) && is_array( $match_scores[ $game ] ) ? $match_scores[ $game ] : array();
			$first      = isset( $game_score[0] ) ? trim( (string) $game_score[0] ) : '';
			$second     = isset( $game_score[1] ) ? trim( (string) $game_score[1] ) : '';

			if ( '' === $first && '' === $second ) {
				$has_blank   = true;
				$validated[] = array( '', '' );
				continue;
			}

			if ( '' === $first || '' === $second || ! preg_match( '/^[0-9]+$/', $first ) || ! preg_match( '/^[0-9]+$/', $second ) ) {
				return new WP_Error( 'invalid_score', __( 'Each entered game must contain two nonnegative whole-number scores.', 'table-tennis-tournament-for-clubs' ) );
			}

			if ( max( $wins ) >= $required ) {
				return new WP_Error( 'extra_game', __( 'Scores cannot be entered for games after the match has been decided.', 'table-tennis-tournament-for-clubs' ) );
			}

			if ( $has_blank ) {
				return new WP_Error( 'skipped_game', __( 'Games must be entered in order without skipping games.', 'table-tennis-tournament-for-clubs' ) );
			}

			$first_score  = (int) $first;
			$second_score = (int) $second;
			$high         = max( $first_score, $second_score );
			$low          = min( $first_score, $second_score );

			$is_valid_game = ( 11 === $high && $low <= 9 ) || ( $high > 11 && 2 === ( $high - $low ) );

			if ( ! $is_valid_game ) {
				return new WP_Error( 'invalid_game', __( 'A game must be won with 11 points (and at least a two-point margin) or beyond 10-10 with a lead of exactly two points.', 'table-tennis-tournament-for-clubs' ) );
			}

			$has_score = true;
			$wins[ $first_score > $second_score ? 0 : 1 ]++;
			$validated[] = array( (string) $first_score, (string) $second_score );
		}

		if ( $has_score && max( $wins ) < $required ) {
			return new WP_Error( 'incomplete_match', sprintf( __( 'A Best of %d match must have a winner with at least %d games won.', 'table-tennis-tournament-for-clubs' ), $games, $required ) );
		}

		return $validated;
	}

	private function score_error_transient_key( $tournament_id ) {
		return 'tttc_score_error_' . get_current_user_id() . '_' . absint( $tournament_id );
	}

	private function form_scores( $submitted, $schedule, $games ) {
		$scores = array();

		foreach ( $schedule as $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round ) {
				foreach ( $round as $match ) {
					$match_key    = $this->match_key( $match[0]->ID, $match[1]->ID );
					$match_scores = isset( $submitted[ $match_key ] ) && is_array( $submitted[ $match_key ] ) ? $submitted[ $match_key ] : array();
					$scores[ $match_key ] = array();
					for ( $game = 0; $game < $games; $game++ ) {
						$game_score              = isset( $match_scores[ $game ] ) && is_array( $match_scores[ $game ] ) ? $match_scores[ $game ] : array();
						$scores[ $match_key ][] = array(
							isset( $game_score[0] ) && is_scalar( $game_score[0] ) ? sanitize_text_field( $game_score[0] ) : '',
							isset( $game_score[1] ) && is_scalar( $game_score[1] ) ? sanitize_text_field( $game_score[1] ) : '',
						);
					}
				}
			}
		}

		return $scores;
	}

	private function saved_scores( $tournament_id ) {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT match_key, scores FROM ' . TTTC_Plugin::scores_table_name() . ' WHERE tournament_id = %d', $tournament_id ) );
		$scores = array();
		foreach ( $rows as $row ) {
			$decoded = json_decode( $row->scores, true );
			$scores[ $row->match_key ] = array();
			if ( ! is_array( $decoded ) ) {
				continue;
			}
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

	private function match_key( $player_one_id, $player_two_id ) {
		$player_ids = array( absint( $player_one_id ), absint( $player_two_id ) );
		sort( $player_ids, SORT_NUMERIC );

		return $player_ids[0] . '-' . $player_ids[1];
	}

	public function assignments_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0;
		$tournament_type = $tournament_id ? get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_TYPE, true ) : 'both';
		$tournament_type = in_array( $tournament_type, array( 'senior', 'youth', 'both' ), true ) ? $tournament_type : 'both';
		$players         = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => array( array( 'key' => TTTC_Plugin::PLAYER_META_ACTIVE, 'value' => '1' ) ) ) );
		$assigned      = $tournament_id ? $this->assigned_player_ids( $tournament_id ) : array();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Tournament Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tournament players updated.', 'table-tennis-tournament-for-clubs' ); ?></p></div><?php endif; ?>
			<?php if ( $tournament_id ) : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-assignment-form">
					<input type="hidden" name="action" value="tttc_update_players"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
					<?php wp_nonce_field( 'tttc_update_players', 'tttc_assignment_nonce' ); ?>
					<h2><?php echo esc_html( get_the_title( $tournament_id ) ); ?></h2>
					<p><?php esc_html_e( 'Select the active players who will participate in this tournament.', 'table-tennis-tournament-for-clubs' ); ?></p>
					<?php if ( 'both' !== $tournament_type ) : ?>
						<p><label><input type="checkbox" class="tttc-show-all-players"> <?php esc_html_e( 'Show all players (including other player types)', 'table-tennis-tournament-for-clubs' ); ?></label></p>
					<?php endif; ?>
					<table class="widefat striped" data-tournament-type="<?php echo esc_attr( $tournament_type ); ?>"><thead><tr><th class="check-column"><input type="checkbox" class="tttc-select-all"></th><th><?php esc_html_e( 'Player', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead><tbody>
					<?php
					foreach ( $players as $player ) :
						$player_type = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
						$player_type = $player_type ? $player_type : 'senior';
						$is_assigned = in_array( $player->ID, $assigned, true );
						$type_mismatch = 'both' !== $tournament_type && $player_type !== $tournament_type;
						?>
						<tr data-type="<?php echo esc_attr( $player_type ); ?>" class="<?php echo $type_mismatch && ! $is_assigned ? 'tttc-row-hidden-by-type' : ''; ?>"><th class="check-column"><input type="checkbox" name="player_ids[]" value="<?php echo esc_attr( $player->ID ); ?>" <?php checked( $is_assigned ); ?>></th><td><?php echo esc_html( $player->post_title ); ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td></tr>
					<?php endforeach; ?>
					</tbody></table><p><button class="button button-primary"><?php esc_html_e( 'Save tournament players', 'table-tennis-tournament-for-clubs' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function order_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0;
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Order Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<h2><?php echo esc_html( get_the_title( $tournament_id ) ); ?></h2>
			<p><a href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-assignments&tournament_id=' . $tournament_id ) ); ?>"><?php esc_html_e( 'Manage', 'table-tennis-tournament-for-clubs' ); ?></a></p>
			<?php $this->render_seed_order_section( $tournament_id ); ?>
		</div>
		<?php
	}

	private function render_seed_order_section( $tournament_id ) {
		$seed_players = $this->seed_ordered_players( $tournament_id );
		if ( count( $seed_players ) < 2 ) {
			return;
		}
		$locked = TTTC_Plugin::has_scores( $tournament_id );
		?>
		<h2><?php esc_html_e( 'Seeding order', 'table-tennis-tournament-for-clubs' ); ?></h2>
		<?php if ( $locked ) : ?>
			<p class="description"><?php esc_html_e( 'Seeding is locked because scores have already been entered for this tournament.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<ol class="tttc-seed-list tttc-seed-list--locked">
				<?php foreach ( $seed_players as $player ) : ?>
					<li><?php echo esc_html( $player->post_title ); ?> <span class="tttc-seed-rating"><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></span></li>
				<?php endforeach; ?>
			</ol>
		<?php else : ?>
			<p><?php esc_html_e( 'Drag players to set the strength order used for grouping. This overrides the rating-based order.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-seed-form">
				<input type="hidden" name="action" value="tttc_reorder_players"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>"><input type="hidden" class="tttc-seed-order-input" name="ordered_player_ids" value="">
				<?php wp_nonce_field( 'tttc_reorder_players_' . $tournament_id, 'tttc_reorder_nonce' ); ?>
				<ol class="tttc-seed-list" data-tournament-id="<?php echo esc_attr( $tournament_id ); ?>">
					<?php foreach ( $seed_players as $player ) : ?>
						<li data-player-id="<?php echo esc_attr( $player->ID ); ?>"><span class="tttc-seed-handle" aria-hidden="true">&#9776;</span> <?php echo esc_html( $player->post_title ); ?> <span class="tttc-seed-rating"><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></span></li>
					<?php endforeach; ?>
				</ol>
				<p><button class="button button-primary"><?php esc_html_e( 'Save order', 'table-tennis-tournament-for-clubs' ); ?></button></p>
			</form>
		<?php endif;
	}

	public function update_players() {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_assignment_nonce'] ) ), 'tttc_update_players' ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		$player_ids    = isset( $_POST['player_ids'] ) ? array_map( 'absint', (array) $_POST['player_ids'] ) : array();
		$active_ids    = array();
		foreach ( $player_ids as $player_id ) {
			if ( TTTC_Plugin::PLAYER_POST_TYPE === get_post_type( $player_id ) && '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ) {
				$active_ids[] = $player_id;
			}
		}
		$active_ids = array_values( array_unique( $active_ids ) );

		global $wpdb;
		$table          = TTTC_Plugin::table_name();
		$existing_seeds = array();
		foreach ( $wpdb->get_results( $wpdb->prepare( "SELECT player_id, seed FROM {$table} WHERE tournament_id = %d", $tournament_id ) ) as $row ) {
			$existing_seeds[ (int) $row->player_id ] = null === $row->seed ? null : (int) $row->seed;
		}

		// Players already assigned keep their relative seed order; newly added players are appended by rating.
		$kept_ids = array_values( array_intersect( $active_ids, array_keys( $existing_seeds ) ) );
		usort( $kept_ids, function ( $first, $second ) use ( $existing_seeds ) {
			$first_seed  = null !== $existing_seeds[ $first ] ? $existing_seeds[ $first ] : PHP_INT_MAX;
			$second_seed = null !== $existing_seeds[ $second ] ? $existing_seeds[ $second ] : PHP_INT_MAX;
			return $first_seed <=> $second_seed;
		} );

		$new_ids = array_values( array_diff( $active_ids, $kept_ids ) );
		usort( $new_ids, function ( $first, $second ) {
			$rating_difference = absint( get_post_meta( $second, TTTC_Plugin::PLAYER_META_RATING, true ) ) - absint( get_post_meta( $first, TTTC_Plugin::PLAYER_META_RATING, true ) );
			return 0 !== $rating_difference ? $rating_difference : strcasecmp( get_the_title( $first ), get_the_title( $second ) );
		} );

		$ordered_ids = array_merge( $kept_ids, $new_ids );

		$wpdb->delete( $table, array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		$wpdb->delete( TTTC_Plugin::scores_table_name(), array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		foreach ( $ordered_ids as $index => $player_id ) {
			$rating_meta = get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true );
			$rating      = '' === $rating_meta ? null : absint( $rating_meta );
			$wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'player_id' => $player_id, 'rating' => $rating, 'seed' => $index + 1, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%d', '%d', '%s' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'updated' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	public function reorder_players() {
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_reorder_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_reorder_nonce'] ) ), 'tttc_reorder_players_' . $tournament_id ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || TTTC_Plugin::has_scores( $tournament_id ) ) {
			wp_die( esc_html__( 'The seeding order can not be changed for this tournament.', 'table-tennis-tournament-for-clubs' ) );
		}

		global $wpdb;
		$table       = TTTC_Plugin::table_name();
		$current_ids = array();
		foreach ( $wpdb->get_col( $wpdb->prepare( "SELECT player_id FROM {$table} WHERE tournament_id = %d", $tournament_id ) ) as $player_id ) {
			$player_id = (int) $player_id;
			if ( '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ) {
				$current_ids[] = $player_id;
			}
		}

		$submitted   = isset( $_POST['ordered_player_ids'] ) ? sanitize_text_field( wp_unslash( $_POST['ordered_player_ids'] ) ) : '';
		$ordered_ids = array_values( array_filter( array_map( 'absint', explode( ',', $submitted ) ) ) );

		$sorted_current = $current_ids;
		$sorted_ordered = $ordered_ids;
		sort( $sorted_current );
		sort( $sorted_ordered );
		if ( empty( $ordered_ids ) || $sorted_current !== $sorted_ordered ) {
			wp_die( esc_html__( 'The submitted player order is invalid.', 'table-tennis-tournament-for-clubs' ) );
		}

		foreach ( $ordered_ids as $index => $player_id ) {
			$wpdb->update( $table, array( 'seed' => $index + 1 ), array( 'tournament_id' => $tournament_id, 'player_id' => $player_id ), array( '%d' ), array( '%d', '%d' ) );
		}

		wp_safe_redirect( add_query_arg( array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'updated' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	private function assigned_player_ids( $tournament_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d', $tournament_id ) ) );
	}

	private function seed_ordered_players( $tournament_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, seed FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d', $tournament_id ) );
		usort( $rows, function ( $first, $second ) {
			$first_seed  = null !== $first->seed ? (int) $first->seed : PHP_INT_MAX;
			$second_seed = null !== $second->seed ? (int) $second->seed : PHP_INT_MAX;
			return $first_seed <=> $second_seed;
		} );

		$players = array();
		foreach ( $rows as $row ) {
			$player_id = (int) $row->player_id;
			if ( '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ) {
				$player = get_post( $player_id );
				if ( $player ) {
					$players[] = $player;
				}
			}
		}

		return $players;
	}

	public function player_bulk_actions( $actions ) {
		$actions['tttc_merge_players'] = __( 'Merge selected players', 'table-tennis-tournament-for-clubs' );

		return $actions;
	}

	public function handle_player_merge_bulk_action( $redirect_to, $action, $post_ids ) {
		if ( 'tttc_merge_players' !== $action ) {
			return $redirect_to;
		}

		$post_ids = array_map( 'absint', $post_ids );
		$valid    = count( $post_ids ) === 2;
		foreach ( $post_ids as $post_id ) {
			if ( TTTC_Plugin::PLAYER_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
				$valid = false;
			}
		}

		if ( ! $valid ) {
			return add_query_arg( array( 'tttc_merge_error' => 'count' ), $redirect_to );
		}

		return add_query_arg(
			array(
				'page'       => 'tttc-merge-players',
				'player_ids' => $post_ids,
			),
			admin_url( 'admin.php' )
		);
	}

	public function merge_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$player_ids = isset( $_GET['player_ids'] ) ? array_map( 'absint', (array) $_GET['player_ids'] ) : array();
		$player_ids = array_values( array_unique( $player_ids ) );

		if ( 2 !== count( $player_ids ) ) {
			wp_die( esc_html__( 'Select exactly two players to merge.', 'table-tennis-tournament-for-clubs' ) );
		}

		foreach ( $player_ids as $player_id ) {
			if ( TTTC_Plugin::PLAYER_POST_TYPE !== get_post_type( $player_id ) ) {
				wp_die( esc_html__( 'The selected players could not be found.', 'table-tennis-tournament-for-clubs' ) );
			}
		}
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Merge Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<?php if ( isset( $_GET['tttc_merge_error'] ) ) : ?>
				<div class="notice notice-error"><p><?php esc_html_e( 'Select exactly two players to merge.', 'table-tennis-tournament-for-clubs' ); ?></p></div>
			<?php endif; ?>
			<p><?php esc_html_e( 'Choose which player record to keep. The other player will be permanently deleted and its tournament assignments and scores will be transferred to the player you keep.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="tttc_merge_players">
				<?php foreach ( $player_ids as $player_id ) : ?>
					<input type="hidden" name="player_ids[]" value="<?php echo esc_attr( $player_id ); ?>">
				<?php endforeach; ?>
				<?php wp_nonce_field( 'tttc_merge_players', 'tttc_merge_nonce' ); ?>
				<table class="widefat striped">
					<thead><tr><th class="check-column"><?php esc_html_e( 'Keep', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Name', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Active', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Tournaments', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead>
					<tbody>
					<?php foreach ( $player_ids as $index => $player_id ) : ?>
						<tr>
							<th class="check-column"><input type="radio" name="keep_player_id" value="<?php echo esc_attr( $player_id ); ?>" <?php checked( 0 === $index ); ?> required></th>
							<td><?php echo esc_html( get_the_title( $player_id ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td>
							<td><?php echo esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td>
							<td><?php echo esc_html( 'female' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_GENDER, true ) ? __( 'Female', 'table-tennis-tournament-for-clubs' ) : __( 'Male', 'table-tennis-tournament-for-clubs' ) ); ?></td>
							<td><?php echo esc_html( 'youth' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_TYPE, true ) ? __( 'Youth', 'table-tennis-tournament-for-clubs' ) : __( 'Senior', 'table-tennis-tournament-for-clubs' ) ); ?></td>
							<td><?php echo '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ? esc_html__( 'Yes', 'table-tennis-tournament-for-clubs' ) : esc_html__( 'No', 'table-tennis-tournament-for-clubs' ); ?></td>
							<td><?php echo esc_html( count( $this->assigned_player_ids_for_player( $player_id ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
				<p><button class="button button-primary"><?php esc_html_e( 'Merge players', 'table-tennis-tournament-for-clubs' ); ?></button></p>
			</form>
		</div>
		<?php
	}

	public function merge_players() {
		if ( ! isset( $_POST['tttc_merge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_merge_nonce'] ) ), 'tttc_merge_players' ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}

		$player_ids = isset( $_POST['player_ids'] ) ? array_map( 'absint', (array) $_POST['player_ids'] ) : array();
		$player_ids = array_values( array_unique( $player_ids ) );
		$keep_id    = isset( $_POST['keep_player_id'] ) ? absint( $_POST['keep_player_id'] ) : 0;

		if ( 2 !== count( $player_ids ) || ! in_array( $keep_id, $player_ids, true ) ) {
			wp_die( esc_html__( 'The selected players could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		foreach ( $player_ids as $player_id ) {
			if ( TTTC_Plugin::PLAYER_POST_TYPE !== get_post_type( $player_id ) ) {
				wp_die( esc_html__( 'The selected players could not be found.', 'table-tennis-tournament-for-clubs' ) );
			}
		}

		$source_id = (int) $player_ids[0] === $keep_id ? (int) $player_ids[1] : (int) $player_ids[0];

		if ( ! current_user_can( 'edit_post', $keep_id ) || ! current_user_can( 'delete_post', $source_id ) ) {
			wp_die( esc_html__( 'You do not have permission to merge these players.', 'table-tennis-tournament-for-clubs' ) );
		}

		$this->merge_player_data( $keep_id, $source_id );
		wp_delete_post( $source_id, true );

		wp_safe_redirect( admin_url( 'edit.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE . '&tttc_merged=1' ) );
		exit;
	}

	private function merge_player_data( $keep_id, $source_id ) {
		global $wpdb;
		$assign_table = TTTC_Plugin::table_name();
		$scores_table = TTTC_Plugin::scores_table_name();

		$tournament_ids = $wpdb->get_col( $wpdb->prepare( "SELECT tournament_id FROM {$assign_table} WHERE player_id = %d", $source_id ) );
		foreach ( $tournament_ids as $tournament_id ) {
			$already_assigned = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$assign_table} WHERE tournament_id = %d AND player_id = %d", $tournament_id, $keep_id ) );
			if ( $already_assigned ) {
				$wpdb->delete( $assign_table, array( 'tournament_id' => $tournament_id, 'player_id' => $source_id ), array( '%d', '%d' ) );
			} else {
				$wpdb->update( $assign_table, array( 'player_id' => $keep_id ), array( 'tournament_id' => $tournament_id, 'player_id' => $source_id ), array( '%d' ), array( '%d', '%d' ) );
			}
		}

		$rows = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$scores_table} WHERE player_one_id = %d OR player_two_id = %d", $source_id, $source_id ) );
		foreach ( $rows as $row ) {
			$opponent_id = (int) $row->player_one_id === (int) $source_id ? (int) $row->player_two_id : (int) $row->player_one_id;

			// The merged pair played each other, so this match no longer makes sense.
			if ( $opponent_id === (int) $keep_id ) {
				$wpdb->delete( $scores_table, array( 'id' => $row->id ), array( '%d' ) );
				continue;
			}

			$new_match_key = $this->match_key( $keep_id, $opponent_id );
			$existing      = $wpdb->get_var( $wpdb->prepare( "SELECT id FROM {$scores_table} WHERE tournament_id = %d AND match_key = %s AND id != %d", $row->tournament_id, $new_match_key, $row->id ) );

			if ( $existing ) {
				$wpdb->delete( $scores_table, array( 'id' => $row->id ), array( '%d' ) );
				continue;
			}

			$wpdb->update(
				$scores_table,
				array(
					'player_one_id' => min( $keep_id, $opponent_id ),
					'player_two_id' => max( $keep_id, $opponent_id ),
					'match_key'     => $new_match_key,
				),
				array( 'id' => $row->id ),
				array( '%d', '%d', '%s' ),
				array( '%d' )
			);
		}
	}

	public function merge_admin_notices() {
		$screen = get_current_screen();
		if ( ! $screen || 'edit-' . TTTC_Plugin::PLAYER_POST_TYPE !== $screen->id ) {
			return;
		}

		if ( isset( $_GET['tttc_merged'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Players merged. The duplicate player has been deleted and its tournaments and scores were transferred.', 'table-tennis-tournament-for-clubs' ) . '</p></div>';
		} elseif ( isset( $_GET['tttc_merge_error'] ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Select exactly two players to merge.', 'table-tennis-tournament-for-clubs' ) . '</p></div>';
		}
	}

	private function assigned_player_ids_for_player( $player_id ) {
		global $wpdb;
		return $wpdb->get_col( $wpdb->prepare( 'SELECT tournament_id FROM ' . TTTC_Plugin::table_name() . ' WHERE player_id = %d', $player_id ) );
	}

	public function enqueue_assets( $hook ) {
		$screen           = get_current_screen();
		$screen_post_type = $screen ? $screen->post_type : '';
		if ( ! $screen_post_type && isset( $_GET['post_type'] ) ) {
			$screen_post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}

		if ( false !== strpos( $hook, 'tttc-' ) || in_array( $screen_post_type, array( TTTC_Plugin::PLAYER_POST_TYPE, TTTC_Plugin::TOURNAMENT_POST_TYPE ), true ) ) {
			wp_enqueue_style( 'tttc-admin', TTTC_URL . 'assets/admin.css', array(), TTTC_VERSION );
			wp_enqueue_script( 'tttc-admin', TTTC_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), TTTC_VERSION, true );

			if ( TTTC_Plugin::PLAYER_POST_TYPE === $screen_post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				$existing_players = get_posts( array(
					'post_type'   => TTTC_Plugin::PLAYER_POST_TYPE,
					'post_status' => array( 'publish', 'draft', 'future', 'pending', 'private' ),
					'numberposts' => -1,
				) );
				$player_data = array();
				foreach ( $existing_players as $p ) {
					$email         = get_post_meta( $p->ID, TTTC_Plugin::PLAYER_META_EMAIL, true );
					$player_data[] = array(
						'id'    => (int) $p->ID,
						'name'  => preg_replace( '/\s+/', ' ', trim( $p->post_title ) ),
						'email' => strtolower( trim( $email ) ),
					);
				}
				$current_post_id = get_the_ID() ? (int) get_the_ID() : ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : 0 );
				wp_localize_script(
					'tttc-admin',
					'tttcAdminData',
					array(
						'players'       => $player_data,
						'currentPostId' => $current_post_id,
						'duplicateMsg'  => __( 'A player with this name and email already exists.', 'table-tennis-tournament-for-clubs' ),
					)
				);
			}

			if ( false !== strpos( $hook, 'tttc-scores' ) ) {
				wp_enqueue_style( 'tttc-public', TTTC_URL . 'assets/public.css', array( 'tttc-admin' ), TTTC_VERSION );
				wp_enqueue_script( 'tttc-public', TTTC_URL . 'assets/public.js', array(), TTTC_VERSION, true );
			}
		}
	}
}
