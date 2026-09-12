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
		add_action( 'add_meta_boxes', array( $this, 'register_meta_boxes' ) );
		add_action( 'save_post_' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'save_player' ) );
		add_action( 'save_post_' . TTTC_Plugin::TOURNAMENT_POST_TYPE, array( $this, 'save_tournament' ) );
		add_filter( 'manage_' . TTTC_Plugin::PLAYER_POST_TYPE . '_posts_columns', array( $this, 'player_columns' ) );
		add_action( 'manage_' . TTTC_Plugin::PLAYER_POST_TYPE . '_posts_custom_column', array( $this, 'player_column' ), 10, 2 );
		add_filter( 'manage_' . TTTC_Plugin::TOURNAMENT_POST_TYPE . '_posts_columns', array( $this, 'tournament_columns' ) );
		add_action( 'manage_' . TTTC_Plugin::TOURNAMENT_POST_TYPE . '_posts_custom_column', array( $this, 'tournament_column' ), 10, 2 );
		add_action( 'admin_post_tttc_update_players', array( $this, 'update_players' ) );
		add_action( 'admin_post_tttc_save_scores', array( $this, 'save_scores' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'redirect_post_location', array( $this, 'redirect_after_post_save' ), 10, 2 );
	}

	public function register_menu() {
		add_menu_page(
			__( 'Table Tennis Clubs', 'table-tennis-tournament-for-clubs' ),
			__( 'Table Tennis', 'table-tennis-tournament-for-clubs' ),
			'edit_posts',
			'tttc-dashboard',
			array( $this, 'dashboard' ),
			'dashicons-awards',
			80
		);
		add_submenu_page( 'tttc-dashboard', __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-dashboard', array( $this, 'dashboard' ) );
		add_submenu_page( 'tttc-dashboard', __( 'Players', 'table-tennis-tournament-for-clubs' ), __( 'Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE );
		add_submenu_page( 'tttc-dashboard', __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::TOURNAMENT_POST_TYPE );
		add_submenu_page( null, __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-assignments', array( $this, 'assignments_page' ) );
		add_submenu_page( null, __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-scores', array( $this, 'scores_page' ) );
	}

	public function dashboard() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$player_count     = wp_count_posts( TTTC_Plugin::PLAYER_POST_TYPE )->publish;
		$tournament_count = wp_count_posts( TTTC_Plugin::TOURNAMENT_POST_TYPE )->publish;
		?>
		<div class="wrap tttc-dashboard">
			<h1><?php esc_html_e( 'Table Tennis Tournament for Clubs', 'table-tennis-tournament-for-clubs' ); ?></h1>
			<p><?php esc_html_e( 'Manage your club players and tournaments from one place.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<div class="tttc-summary-grid">
				<div class="tttc-summary-card"><span class="dashicons dashicons-groups"></span><strong><?php echo esc_html( $player_count ); ?></strong><span><?php esc_html_e( 'Published players', 'table-tennis-tournament-for-clubs' ); ?></span></div>
				<div class="tttc-summary-card"><span class="dashicons dashicons-awards"></span><strong><?php echo esc_html( $tournament_count ); ?></strong><span><?php esc_html_e( 'Published tournaments', 'table-tennis-tournament-for-clubs' ); ?></span></div>
			</div>
			<p><a class="button button-primary" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add player', 'table-tennis-tournament-for-clubs' ); ?></a> <a class="button" href="<?php echo esc_url( admin_url( 'post-new.php?post_type=' . TTTC_Plugin::TOURNAMENT_POST_TYPE ) ); ?>"><?php esc_html_e( 'Add tournament', 'table-tennis-tournament-for-clubs' ); ?></a></p>
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
		$type   = $type ? $type : 'both';
		$games  = in_array( (string) $games, array( '3', '5' ), true ) ? (string) $games : '3';
		?>
		<p><label for="tttc-date"><strong><?php esc_html_e( 'Date', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input type="date" id="tttc-date" name="tttc_date" value="<?php echo esc_attr( $date ); ?>" required></p>
		<p><label for="tttc-games"><strong><?php esc_html_e( 'Best of', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-games" name="tttc_games" required><option value="3" <?php selected( $games, '3' ); ?>>3</option><option value="5" <?php selected( $games, '5' ); ?>>5</option></select></p>
		<p><label for="tttc-type"><strong><?php esc_html_e( 'Player type', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-type" name="tttc_type"><option value="senior" <?php selected( $type, 'senior' ); ?>><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option><option value="youth" <?php selected( $type, 'youth' ); ?>><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option><option value="both" <?php selected( $type, 'both' ); ?>><?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></option></select></p>
		<p><label for="tttc-status"><strong><?php esc_html_e( 'Status', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select id="tttc-status" name="tttc_status">
			<?php foreach ( TTTC_Plugin::statuses() as $key => $label ) : ?>
				<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>><?php echo esc_html( $label ); ?></option>
			<?php endforeach; ?>
		</select></p>
		<?php
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
		$games  = isset( $_POST['tttc_games'] ) ? sanitize_key( wp_unslash( $_POST['tttc_games'] ) ) : '3';
		$type   = isset( $_POST['tttc_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_type'] ) ) : 'both';
		$status = isset( $_POST['tttc_status'] ) ? sanitize_key( $_POST['tttc_status'] ) : 'draft';
		if ( ! in_array( $type, array( 'senior', 'youth', 'both' ), true ) ) {
			$type = 'both';
		}
		if ( ! in_array( $games, array( '3', '5' ), true ) ) {
			$games = '3';
		}
		if ( ! array_key_exists( $status, TTTC_Plugin::statuses() ) ) {
			$status = 'draft';
		}
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, $date );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, $games );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TYPE, $type );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, $status );
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
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_gender' => __( 'Gender', 'table-tennis-tournament-for-clubs' ), 'tttc_type' => __( 'Type', 'table-tennis-tournament-for-clubs' ), 'tttc_rating' => __( 'Rating', 'table-tennis-tournament-for-clubs' ), 'tttc_email' => __( 'Email', 'table-tennis-tournament-for-clubs' ), 'tttc_active' => __( 'Active', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
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
		}
	}

	public function tournament_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_date' => __( 'Date', 'table-tennis-tournament-for-clubs' ), 'tttc_games' => __( 'Best of', 'table-tennis-tournament-for-clubs' ), 'tttc_status' => __( 'Status', 'table-tennis-tournament-for-clubs' ), 'tttc_players' => __( 'Players', 'table-tennis-tournament-for-clubs' ), 'tttc_url' => __( 'Url', 'table-tennis-tournament-for-clubs' ), 'tttc_scores' => __( 'Scores', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
	}

	public function tournament_column( $column, $post_id ) {
		if ( 'tttc_date' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true ) );
		} elseif ( 'tttc_games' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true ) );
		} elseif ( 'tttc_status' === $column ) {
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			echo esc_html( isset( TTTC_Plugin::statuses()[ $status ] ) ? TTTC_Plugin::statuses()[ $status ] : $status );
		} elseif ( 'tttc_players' === $column ) {
			$count = $this->assigned_player_ids( $post_id );
			echo esc_html( count( $count ) ) . ' <a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-assignments&tournament_id=' . $post_id ) ) . '">' . esc_html__( 'Manage players', 'table-tennis-tournament-for-clubs' ) . '</a>';
		} elseif ( 'tttc_url' === $column ) {
			$website_url = $this->tournament_url( $post_id );
			if ( $website_url ) {
				echo '<a class="button-link" href="' . esc_url( $website_url ) . '" target="_blank" rel="noopener noreferrer">' . esc_html__( 'Website', 'table-tennis-tournament-for-clubs' ) . '</a>';
			}
		} elseif ( 'tttc_scores' === $column ) {
			echo '<a class="button-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $post_id ) ) . '">' . esc_html__( 'Enter scores', 'table-tennis-tournament-for-clubs' ) . '</a>';
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

		$games       = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$games       = in_array( (string) $games, array( '3', '5' ), true ) ? (int) $games : 3;
		$schedule    = TTTC_Public::instance()->tournament_schedule( $tournament_id );
		$error_data  = get_transient( $this->score_error_transient_key( $tournament_id ) );
		if ( is_array( $error_data ) ) {
			delete_transient( $this->score_error_transient_key( $tournament_id ) );
			$this->score_error       = isset( $error_data['message'] ) ? $error_data['message'] : '';
			$this->score_form_scores = isset( $error_data['scores'] ) ? $error_data['scores'] : null;
		}
		$saved_scores = null !== $this->score_form_scores ? $this->score_form_scores : $this->saved_scores( $tournament_id );
		?>
		<div class="wrap">
			<h1><?php echo esc_html( get_the_title( $tournament_id ) . ' - ' . __( 'Scores', 'table-tennis-tournament-for-clubs' ) ); ?></h1>
			<?php if ( $this->score_error ) : ?><div class="notice notice-error"><p><?php echo esc_html( $this->score_error ); ?></p></div><?php endif; ?>
			<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tournament scores updated.', 'table-tennis-tournament-for-clubs' ); ?></p></div><?php endif; ?>
			<?php if ( empty( $schedule ) ) : ?>
				<p><?php esc_html_e( 'A score sheet is available when the tournament has 4 to 28 assigned active players.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<?php else : ?>
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-scores-form">
					<input type="hidden" name="action" value="tttc_save_scores"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
					<?php wp_nonce_field( 'tttc_save_scores', 'tttc_scores_nonce' ); ?>
					<?php foreach ( $schedule as $group_index => $group_schedule ) : ?>
						<h2><?php echo esc_html( sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $group_index + 1 ) ); ?></h2>
						<div class="tttc-scores-table-wrap"><table class="widefat striped tttc-scores-table"><thead><tr><th><?php esc_html_e( 'Round', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?></tr></thead><tbody>
						<?php foreach ( $group_schedule['rounds'] as $round_number => $round ) : ?>
							<?php foreach ( $round as $match_number => $match ) : $match_key = $this->match_key( $match[0]->ID, $match[1]->ID ); ?>
								<tr><td><?php echo esc_html( $round_number + 1 ); ?></td><td><?php echo esc_html( $match_number + 1 ); ?></td><td><?php echo esc_html( $match[0]->post_title ); ?></td><td><?php echo esc_html( $match[1]->post_title ); ?></td><?php for ( $game = 0; $game < $games; $game++ ) : ?><td><span class="tttc-score-pair"><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][0]" value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][0] ) ? $saved_scores[ $match_key ][ $game ][0] : '' ); ?>"><input class="small-text" type="number" min="0" name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][1]" value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][1] ) ? $saved_scores[ $match_key ][ $game ][1] : '' ); ?>"></span></td><?php endfor; ?></tr>
							<?php endforeach; ?>
						<?php endforeach; ?></tbody></table></div>
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

		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&updated=1' ) );
		exit;
	}

	private function validate_match_scores( $match_scores, $games ) {
		$validated = array();
		$wins      = array( 0, 0 );
		$has_score = false;
		$required  = (int) ceil( $games / 2 );

		for ( $game = 0; $game < $games; $game++ ) {
			$game_score = isset( $match_scores[ $game ] ) && is_array( $match_scores[ $game ] ) ? $match_scores[ $game ] : array();
			$first      = isset( $game_score[0] ) ? trim( (string) $game_score[0] ) : '';
			$second     = isset( $game_score[1] ) ? trim( (string) $game_score[1] ) : '';

			if ( '' === $first && '' === $second ) {
				$validated[] = array( '', '' );
				continue;
			}

			if ( '' === $first || '' === $second || ! preg_match( '/^[0-9]+$/', $first ) || ! preg_match( '/^[0-9]+$/', $second ) ) {
				return new WP_Error( 'invalid_score', __( 'Each entered game must contain two nonnegative whole-number scores.', 'table-tennis-tournament-for-clubs' ) );
			}

			$first_score  = (int) $first;
			$second_score = (int) $second;
			$has_score    = true;

			if ( $first_score === $second_score || abs( $first_score - $second_score ) < 2 || max( $first_score, $second_score ) < 11 ) {
				return new WP_Error( 'invalid_game', __( 'A game must be won with at least 11 points and a two-point margin.', 'table-tennis-tournament-for-clubs' ) );
			}

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
		$players         = array_filter(
			$players,
			function ( $player ) use ( $tournament_type ) {
				$player_type = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
				$player_type = $player_type ? $player_type : 'senior';

				return 'both' === $tournament_type || $player_type === $tournament_type;
			}
		);
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
					<table class="widefat striped"><thead><tr><th class="check-column"><input type="checkbox" class="tttc-select-all"></th><th><?php esc_html_e( 'Player', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></th></tr></thead><tbody>
					<?php foreach ( $players as $player ) : ?><tr><th class="check-column"><input type="checkbox" name="player_ids[]" value="<?php echo esc_attr( $player->ID ); ?>" <?php checked( in_array( $player->ID, $assigned, true ) ); ?>></th><td><?php echo esc_html( $player->post_title ); ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td></tr><?php endforeach; ?>
					</tbody></table><p><button class="button button-primary"><?php esc_html_e( 'Save tournament players', 'table-tennis-tournament-for-clubs' ); ?></button></p>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	public function update_players() {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_assignment_nonce'] ) ), 'tttc_update_players' ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		$player_ids    = isset( $_POST['player_ids'] ) ? array_map( 'absint', (array) $_POST['player_ids'] ) : array();
		$tournament_type = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
		$tournament_type = in_array( $tournament_type, array( 'senior', 'youth', 'both' ), true ) ? $tournament_type : 'both';
		$active_ids    = array();
		foreach ( $player_ids as $player_id ) {
			$player_type = get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_TYPE, true );
			$player_type = $player_type ? $player_type : 'senior';
			if ( TTTC_Plugin::PLAYER_POST_TYPE === get_post_type( $player_id ) && '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) && ( 'both' === $tournament_type || $player_type === $tournament_type ) ) {
				$active_ids[] = $player_id;
			}
		}
		global $wpdb;
		$table = TTTC_Plugin::table_name();
		$wpdb->delete( $table, array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		$wpdb->delete( TTTC_Plugin::scores_table_name(), array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		foreach ( array_unique( $active_ids ) as $player_id ) {
			$wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'player_id' => $player_id, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'post_type' => TTTC_Plugin::TOURNAMENT_POST_TYPE, 'updated' => '1' ), admin_url( 'edit.php' ) ) );
		exit;
	}

	private function assigned_player_ids( $tournament_id ) {
		global $wpdb;
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM ' . TTTC_Plugin::table_name() . ' WHERE tournament_id = %d', $tournament_id ) ) );
	}

	public function enqueue_assets( $hook ) {
		if ( false !== strpos( $hook, 'tttc-' ) || ( isset( $_GET['post_type'] ) && in_array( $_GET['post_type'], array( TTTC_Plugin::PLAYER_POST_TYPE, TTTC_Plugin::TOURNAMENT_POST_TYPE ), true ) ) ) {
			wp_enqueue_style( 'tttc-admin', TTTC_URL . 'assets/admin.css', array(), TTTC_VERSION );
			wp_enqueue_script( 'tttc-admin', TTTC_URL . 'assets/admin.js', array( 'jquery' ), TTTC_VERSION, true );
		}
	}
}
