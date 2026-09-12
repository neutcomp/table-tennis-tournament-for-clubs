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
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'redirect_post_location', array( $this, 'redirect_after_new_post' ), 10, 2 );
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
		?>
		<p><label for="tttc-rating"><strong><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input class="small-text" type="number" min="0" step="1" id="tttc-rating" name="tttc_rating" value="<?php echo esc_attr( $rating ); ?>" required></p>
		<p><label for="tttc-email"><strong><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input class="regular-text" type="email" id="tttc-email" name="tttc_email" value="<?php echo esc_attr( $email ); ?>"></p>
		<p><label><input type="checkbox" name="tttc_active" value="1" <?php checked( '1', $active ? $active : '1' ); ?>> <?php esc_html_e( 'Player is active and available for new tournament assignments', 'table-tennis-tournament-for-clubs' ); ?></label></p>
		<?php
	}

	public function tournament_meta_box( $post ) {
		wp_nonce_field( 'tttc_save_tournament', 'tttc_tournament_nonce' );
		$date   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$games  = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_GAMES, true );
		$status = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
		$status = $status ? $status : 'draft';
		?>
		<p><label for="tttc-date"><strong><?php esc_html_e( 'Date', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input type="date" id="tttc-date" name="tttc_date" value="<?php echo esc_attr( $date ); ?>" required></p>
		<p><label for="tttc-games"><strong><?php esc_html_e( 'Amount of games', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input class="small-text" type="number" min="1" step="1" id="tttc-games" name="tttc_games" value="<?php echo esc_attr( $games ); ?>" required></p>
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
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_RATING, $rating );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_EMAIL, $email );
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
		$games  = isset( $_POST['tttc_games'] ) ? max( 1, absint( $_POST['tttc_games'] ) ) : 1;
		$status = isset( $_POST['tttc_status'] ) ? sanitize_key( $_POST['tttc_status'] ) : 'draft';
		if ( ! array_key_exists( $status, TTTC_Plugin::statuses() ) ) {
			$status = 'draft';
		}
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, $date );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, $games );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, $status );
	}

	public function redirect_after_new_post( $location, $post_id ) {
		$post_type = get_post_type( $post_id );
		if ( ! in_array( $post_type, array( TTTC_Plugin::PLAYER_POST_TYPE, TTTC_Plugin::TOURNAMENT_POST_TYPE ), true ) || ! isset( $_POST['original_post_status'] ) || 'auto-draft' !== sanitize_key( wp_unslash( $_POST['original_post_status'] ) ) ) {
			return $location;
		}

		return admin_url( 'post-new.php?post_type=' . $post_type );
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
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_rating' => __( 'Rating', 'table-tennis-tournament-for-clubs' ), 'tttc_email' => __( 'Email', 'table-tennis-tournament-for-clubs' ), 'tttc_active' => __( 'Active', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
	}

	public function player_column( $column, $post_id ) {
		if ( 'tttc_rating' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_RATING, true ) );
		} elseif ( 'tttc_email' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_EMAIL, true ) );
		} elseif ( 'tttc_active' === $column ) {
			echo '1' === get_post_meta( $post_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ? esc_html__( 'Yes', 'table-tennis-tournament-for-clubs' ) : esc_html__( 'No', 'table-tennis-tournament-for-clubs' );
		}
	}

	public function tournament_columns( $columns ) {
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_date' => __( 'Date', 'table-tennis-tournament-for-clubs' ), 'tttc_games' => __( 'Games', 'table-tennis-tournament-for-clubs' ), 'tttc_status' => __( 'Status', 'table-tennis-tournament-for-clubs' ), 'tttc_players' => __( 'Players', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
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
		}
	}

	public function assignments_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0;
		$players       = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ) );
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
					<?php foreach ( $players as $player ) : $active = '1' === get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true ); ?><tr class="<?php echo $active ? '' : 'tttc-inactive'; ?>"><th class="check-column"><input type="checkbox" name="player_ids[]" value="<?php echo esc_attr( $player->ID ); ?>" <?php checked( in_array( $player->ID, $assigned, true ) ); ?> <?php disabled( $active, false ); ?>></th><td><?php echo esc_html( $player->post_title ); ?><?php if ( ! $active ) : ?> <em><?php esc_html_e( '(inactive)', 'table-tennis-tournament-for-clubs' ); ?></em><?php endif; ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td><td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td></tr><?php endforeach; ?>
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
		$active_ids    = array();
		foreach ( $player_ids as $player_id ) {
			if ( TTTC_Plugin::PLAYER_POST_TYPE === get_post_type( $player_id ) && '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ) {
				$active_ids[] = $player_id;
			}
		}
		global $wpdb;
		$table = TTTC_Plugin::table_name();
		$wpdb->delete( $table, array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		foreach ( array_unique( $active_ids ) as $player_id ) {
			$wpdb->insert( $table, array( 'tournament_id' => $tournament_id, 'player_id' => $player_id, 'created_at' => current_time( 'mysql', true ) ), array( '%d', '%d', '%s' ) );
		}
		wp_safe_redirect( add_query_arg( array( 'page' => 'tttc-assignments', 'tournament_id' => $tournament_id, 'updated' => '1' ), admin_url( 'admin.php' ) ) );
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
