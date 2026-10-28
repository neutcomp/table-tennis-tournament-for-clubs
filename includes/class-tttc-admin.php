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
		add_filter( 'post_row_actions', array( $this, 'tournament_row_actions' ), 10, 2 );
		add_action( 'admin_footer-edit.php', array( $this, 'render_status_info_modal' ) );
		add_action( 'admin_post_tttc_update_players', array( $this, 'update_players' ) );
		add_action( 'admin_post_tttc_reorder_players', array( $this, 'reorder_players' ) );
		add_action( 'admin_post_tttc_save_scores', array( $this, 'save_scores' ) );
		add_action( 'admin_post_tttc_random_scores', array( $this, 'random_scores' ) );
		add_action( 'admin_post_tttc_remove_scores', array( $this, 'remove_scores' ) );
		add_action( 'admin_post_tttc_save_placement_pools', array( $this, 'save_placement_pools' ) );
		add_action( 'admin_post_tttc_duplicate_tournament', array( $this, 'duplicate_tournament' ) );
		add_action( 'admin_post_tttc_merge_players', array( $this, 'merge_players' ) );
		add_action( 'wp_ajax_tttc_quick_status', array( $this, 'ajax_quick_status' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );
		add_filter( 'redirect_post_location', array( $this, 'redirect_after_post_save' ), 10, 2 );
		add_filter( 'bulk_actions-edit-' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'player_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-edit-' . TTTC_Plugin::PLAYER_POST_TYPE, array( $this, 'handle_player_merge_bulk_action' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'merge_admin_notices' ) );
	}

	public function register_menu() {
		add_options_page( __( 'Table Tennis Settings', 'table-tennis-tournament-for-clubs' ), __( 'Table Tennis', 'table-tennis-tournament-for-clubs' ), 'manage_options', 'tttc-settings', array( $this, 'settings_page' ) );
		add_menu_page(
			__( 'Table Tennis Clubs', 'table-tennis-tournament-for-clubs' ),
			__( 'Table Tennis', 'table-tennis-tournament-for-clubs' ),
			'edit_posts',
			'tttc-dashboard',
			array( $this, 'dashboard' ),
			'data:image/svg+xml;base64,PHN2ZyB4bWxucz0iaHR0cDovL3d3dy53My5vcmcvMjAwMC9zdmciIHZpZXdCb3g9IjAgMCA2NCA2NCI+PHBhdGggZmlsbD0id2hpdGUiIGQ9Ik0yMS42IDUuNWMtOC45IDMuMS0xMy42IDEyLjktMTAuNSAyMS44IDIuMSA2IDcuMyAxMC4xIDEzLjMgMTEuM2w3LjEgMTcuMmMuOCAxLjkgMyAyLjggNC45IDJsNC4xLTEuN2MxLjktLjggMi44LTMgMi00LjlsLTcuMS0xNy4yYzQuOS0zLjYgNy41LTkuNiA2LjUtMTUuOUM0MC40IDkuMSAzMC41IDIuNCAyMS42IDUuNVoiLz48cGF0aCBmaWxsPSJ3aGl0ZSIgZD0ibTQwLjcgMzkuNCA0LjItMS43IDcuMSAxNy4yLTQuMiAxLjd6Ii8+PGNpcmNsZSBjeD0iNTIiIGN5PSIxNCIgcj0iNSIgZmlsbD0id2hpdGUiLz48L3N2Zz4='
		);
		add_submenu_page( 'tttc-dashboard', __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), __( 'Dashboard', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-dashboard', array( $this, 'dashboard' ) );
		add_submenu_page( 'tttc-dashboard', __( 'Players', 'table-tennis-tournament-for-clubs' ), __( 'Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::PLAYER_POST_TYPE );
		add_submenu_page( 'tttc-dashboard', __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), __( 'Tournaments', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'edit.php?post_type=' . TTTC_Plugin::TOURNAMENT_POST_TYPE );
		add_submenu_page( null, __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-assignments', array( $this, 'assignments_page' ) );
		add_submenu_page( null, __( 'Order Players', 'table-tennis-tournament-for-clubs' ), __( 'Order Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-order', array( $this, 'order_page' ) );
		add_submenu_page( null, __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), __( 'Tournament Scores', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-scores', array( $this, 'scores_page' ) );
		add_submenu_page( null, __( 'Placement Pools', 'table-tennis-tournament-for-clubs' ), __( 'Placement Pools', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-placement-pools', array( $this, 'placement_pools_page' ) );
		add_submenu_page( null, __( 'Merge Players', 'table-tennis-tournament-for-clubs' ), __( 'Merge Players', 'table-tennis-tournament-for-clubs' ), 'edit_posts', 'tttc-merge-players', array( $this, 'merge_page' ) );
	}

	public function register_settings() {
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_FROM, array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_SUBJECT, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tttc_email_settings', TTTC_Plugin::OPTION_EMAIL_BODY, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );

		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_DEFAULT_GAMES, array( 'sanitize_callback' => array( $this, 'sanitize_games_setting' ) ) );
		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_DEFAULT_TYPE, array( 'sanitize_callback' => array( $this, 'sanitize_type_setting' ) ) );
		register_setting( 'tttc_format_settings', TTTC_Plugin::OPTION_FORMAT_RANGES, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_format_ranges' ) ) );
		register_setting( 'tttc_tv_settings', TTTC_Plugin::OPTION_TV_INTERVAL, array( 'sanitize_callback' => array( $this, 'sanitize_tv_interval_setting' ) ) );
		register_setting( 'tttc_url_settings', TTTC_Plugin::OPTION_TOURNAMENT_URL_BASE, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_tournament_url_base' ) ) );

		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_FROM, array( 'sanitize_callback' => 'sanitize_email' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_SUBJECT, array( 'sanitize_callback' => 'sanitize_text_field' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_EMAIL_BODY, array( 'sanitize_callback' => 'sanitize_textarea_field' ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_DEFAULT_GAMES, array( 'sanitize_callback' => array( $this, 'sanitize_games_setting' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_DEFAULT_TYPE, array( 'sanitize_callback' => array( $this, 'sanitize_type_setting' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_FORMAT_RANGES, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_format_ranges' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_TV_INTERVAL, array( 'sanitize_callback' => array( $this, 'sanitize_tv_interval_setting' ) ) );
		register_setting( 'tttc_settings', TTTC_Plugin::OPTION_TOURNAMENT_URL_BASE, array( 'sanitize_callback' => array( 'TTTC_Plugin', 'sanitize_tournament_url_base' ) ) );
		register_setting( 'tttc_payment_settings', TTTC_Plugin::OPTION_BANK_ACCOUNT, array( 'sanitize_callback' => 'sanitize_text_field' ) );
	}

	public function sanitize_games_setting( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( '1', '3', '5' ), true ) ? $value : '3';
	}

	public function sanitize_type_setting( $value ) {
		$value = sanitize_key( (string) $value );

		return in_array( $value, array( 'senior', 'youth', 'both' ), true ) ? $value : 'both';
	}

	public function sanitize_tv_interval_setting( $value ) {
		return min( 300, max( 5, absint( $value ) ) );
	}

	public function settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$active_tab = isset( $_GET['tab'] ) ? sanitize_key( wp_unslash( $_GET['tab'] ) ) : 'email';
		if ( ! in_array( $active_tab, array( 'email', 'formats', 'tv', 'urls', 'payments' ), true ) ) {
			$active_tab = 'email';
		}
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Table Tennis Settings', 'table-tennis-tournament-for-clubs' ); ?></h1>
	<nav class="nav-tab-wrapper">
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=email' ) ); ?>"
			class="nav-tab <?php echo 'email' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=formats' ) ); ?>"
			class="nav-tab <?php echo 'formats' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Tournament Formats', 'table-tennis-tournament-for-clubs' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=tv' ) ); ?>"
			class="nav-tab <?php echo 'tv' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'TV Display', 'table-tennis-tournament-for-clubs' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=urls' ) ); ?>"
			class="nav-tab <?php echo 'urls' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'URLs', 'table-tennis-tournament-for-clubs' ); ?></a>
		<a href="<?php echo esc_url( admin_url( 'options-general.php?page=tttc-settings&tab=payments' ) ); ?>"
			class="nav-tab <?php echo 'payments' === $active_tab ? 'nav-tab-active' : ''; ?>"><?php esc_html_e( 'Payments', 'table-tennis-tournament-for-clubs' ); ?></a>
	</nav>

	<?php if ( 'email' === $active_tab ) :
				$from    = get_option( TTTC_Plugin::OPTION_EMAIL_FROM, get_option( 'admin_email' ) );
				$subject = get_option( TTTC_Plugin::OPTION_EMAIL_SUBJECT, __( 'Signup confirmed for [tournament-name]', 'table-tennis-tournament-for-clubs' ) );
				$body    = get_option( TTTC_Plugin::OPTION_EMAIL_BODY, __( "Hello,\n\nYour signup for [tournament-name] on [tournament-date] at [tournament-time] has been received.\n\nView the tournament: [tournament-link]\n\nWe look forward to seeing you.", 'table-tennis-tournament-for-clubs' ) );
				?>
	<p>
		<?php esc_html_e( 'Configure the confirmation email sent after a player signs up for an Upcoming tournament.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'tttc_email_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label
						for="tttc-email-from"><?php esc_html_e( 'Send from email address', 'table-tennis-tournament-for-clubs' ); ?></label>
				</th>
				<td><input type="email" class="regular-text" id="tttc-email-from"
						name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_FROM ); ?>" value="<?php echo esc_attr( $from ); ?>"
						required>
					<p class="description">
						<?php esc_html_e( 'This address is used in the From header.', 'table-tennis-tournament-for-clubs' ); ?></p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label
						for="tttc-email-subject"><?php esc_html_e( 'Subject', 'table-tennis-tournament-for-clubs' ); ?></label></th>
				<td><input type="text" class="large-text" id="tttc-email-subject"
						name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_SUBJECT ); ?>"
						value="<?php echo esc_attr( $subject ); ?>" required>
					<p class="description">
						<?php esc_html_e( 'Available merge fields: [tournament-name], [tournament-date], [tournament-time], and [tournament-link].', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label
						for="tttc-email-body"><?php esc_html_e( 'Body', 'table-tennis-tournament-for-clubs' ); ?></label></th>
				<td><textarea class="large-text" rows="10" id="tttc-email-body"
						name="<?php echo esc_attr( TTTC_Plugin::OPTION_EMAIL_BODY ); ?>"
						required><?php echo esc_textarea( $body ); ?></textarea>
					<p class="description">
						<?php esc_html_e( 'Available merge fields: [tournament-name], [tournament-date], [tournament-time], and [tournament-link].', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php elseif ( 'formats' === $active_tab ) :
				$default_games = get_option( TTTC_Plugin::OPTION_DEFAULT_GAMES, '3' );
				$default_type  = get_option( TTTC_Plugin::OPTION_DEFAULT_TYPE, 'both' );
				$format_ranges = TTTC_Plugin::get_format_ranges();
				?>
	<p>
		<?php esc_html_e( 'Configure default tournament options and player count brackets for group distribution.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'tttc_format_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label
						for="tttc-default-games"><?php esc_html_e( 'Default Best of', 'table-tennis-tournament-for-clubs' ); ?></label>
				</th>
				<td>
					<select id="tttc-default-games" name="<?php echo esc_attr( TTTC_Plugin::OPTION_DEFAULT_GAMES ); ?>">
						<option value="1" <?php selected( $default_games, '1' ); ?>>1</option>
						<option value="3" <?php selected( $default_games, '3' ); ?>>3</option>
						<option value="5" <?php selected( $default_games, '5' ); ?>>5</option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default match format for newly created tournaments.', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><label
						for="tttc-default-type"><?php esc_html_e( 'Default player type', 'table-tennis-tournament-for-clubs' ); ?></label>
				</th>
				<td>
					<select id="tttc-default-type" name="<?php echo esc_attr( TTTC_Plugin::OPTION_DEFAULT_TYPE ); ?>">
						<option value="senior" <?php selected( $default_type, 'senior' ); ?>>
							<?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
						<option value="youth" <?php selected( $default_type, 'youth' ); ?>>
							<?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
						<option value="both" <?php selected( $default_type, 'both' ); ?>>
							<?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></option>
					</select>
					<p class="description">
						<?php esc_html_e( 'Default player type restriction for newly created tournaments.', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
			<tr>
				<th scope="row"><?php esc_html_e( 'Group player brackets', 'table-tennis-tournament-for-clubs' ); ?></th>
				<td>
					<p class="description" style="margin-bottom: 12px;">
						<?php esc_html_e( 'Set the minimum and maximum active player thresholds for distributing players into 1 to 4 groups.', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
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
											// translators: %d is the number of groups.
											$group_count_label = sprintf( _n( '%d Group', '%d Groups', $g, 'table-tennis-tournament-for-clubs' ), $g );
											?>
							<tr>
								<td><strong><?php echo esc_html( $group_count_label ); ?></strong></td>
								<td><input type="number" class="small-text" min="2" step="1"
										name="<?php echo esc_attr( TTTC_Plugin::OPTION_FORMAT_RANGES . '[' . $g . '][min]' ); ?>"
										value="<?php echo esc_attr( $min ); ?>" required></td>
								<td><input type="number" class="small-text" min="2" step="1"
										name="<?php echo esc_attr( TTTC_Plugin::OPTION_FORMAT_RANGES . '[' . $g . '][max]' ); ?>"
										value="<?php echo esc_attr( $max ); ?>" required></td>
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
	<?php elseif ( 'tv' === $active_tab ) :
		$tv_interval = get_option( TTTC_Plugin::OPTION_TV_INTERVAL, 20 );
		?>
	<p>
		<?php esc_html_e( 'Configure how long each slide is shown in TV mode before advancing to the next slide.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'tttc_tv_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label
						for="tttc-tv-interval"><?php esc_html_e( 'Slide duration', 'table-tennis-tournament-for-clubs' ); ?></label>
				</th>
				<td>
					<input type="number" class="small-text" id="tttc-tv-interval"
						name="<?php echo esc_attr( TTTC_Plugin::OPTION_TV_INTERVAL ); ?>"
						value="<?php echo esc_attr( $tv_interval ); ?>" min="5" max="300" step="1" required>
					<span><?php esc_html_e( 'seconds', 'table-tennis-tournament-for-clubs' ); ?></span>
					<p class="description">
						<?php esc_html_e( 'Choose between 5 and 300 seconds. The default is 20 seconds.', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php elseif ( 'urls' === $active_tab ) :
		$tournament_url_base = TTTC_Plugin::get_tournament_url_base();
		?>
	<p>
		<?php esc_html_e( 'Configure the path segment used in public tournament URLs.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<form method="post" action="options.php">
		<?php settings_fields( 'tttc_url_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label
						for="tttc-tournament-url-base"><?php esc_html_e( 'Tournament URL base', 'table-tennis-tournament-for-clubs' ); ?></label>
				</th>
				<td>
					<input type="text" class="regular-text" id="tttc-tournament-url-base"
						name="<?php echo esc_attr( TTTC_Plugin::OPTION_TOURNAMENT_URL_BASE ); ?>"
						value="<?php echo esc_attr( $tournament_url_base ); ?>" required>
					<p class="description">
						<?php esc_html_e( 'Use one URL path segment without slashes. The default is toernooi. Changing this value replaces the previous tournament URL base.', 'table-tennis-tournament-for-clubs' ); ?>
					</p>
				</td>
			</tr>
		</table>
		<?php submit_button(); ?>
	</form>
	<?php else :
		$bank_account = get_option( TTTC_Plugin::OPTION_BANK_ACCOUNT, '' );
		?>
	<p><?php esc_html_e( 'Configure the bank account shown in payment instructions after signup.', 'table-tennis-tournament-for-clubs' ); ?></p>
	<form method="post" action="options.php">
		<?php settings_fields( 'tttc_payment_settings' ); ?>
		<table class="form-table" role="presentation">
			<tr>
				<th scope="row"><label for="tttc-bank-account"><?php esc_html_e( 'Bankaccount', 'table-tennis-tournament-for-clubs' ); ?></label></th>
				<td><input type="text" class="regular-text" id="tttc-bank-account"
					name="<?php echo esc_attr( TTTC_Plugin::OPTION_BANK_ACCOUNT ); ?>"
					value="<?php echo esc_attr( $bank_account ); ?>"></td>
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
	<p>
		<?php esc_html_e( 'Manage your club players and tournaments from one place.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<div class="tttc-dashboard-grid">
		<div class="tttc-summary-card"><span
				class="dashicons dashicons-groups"></span><strong><?php echo esc_html( $player_count ); ?></strong><span><a
					href="<?php echo esc_url( $players_url ); ?>"><?php esc_html_e( 'Published players', 'table-tennis-tournament-for-clubs' ); ?></a></span>
		</div>
		<div class="tttc-summary-card"><span
				class="dashicons dashicons-awards"></span><strong><?php echo esc_html( $tournament_count ); ?></strong><span><a
					href="<?php echo esc_url( $tournaments_url ); ?>"><?php esc_html_e( 'Published tournaments', 'table-tennis-tournament-for-clubs' ); ?></a></span>
		</div>
	</div>
	<div class="tttc-dashboard-grid">
		<section class="tttc-dashboard-section">
			<h2><a
					href="<?php echo esc_url( $players_url ); ?>"><?php esc_html_e( 'Published players', 'table-tennis-tournament-for-clubs' ); ?></a>
			</h2>
			<div class="tttc-stat-list">
				<div>
					<span><?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['male'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['female'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['senior'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $player_stats['youth'] ); ?></strong>
				</div>
			</div>
			<h3><?php esc_html_e( 'Top 3 players by rating', 'table-tennis-tournament-for-clubs' ); ?></h3>
			<?php if ( empty( $top_players ) ) : ?>
			<p><?php esc_html_e( 'No active published players found.', 'table-tennis-tournament-for-clubs' ); ?></p>
			<?php else : ?>
			<ol class="tttc-top-players">
				<?php foreach ( $top_players as $player ) :
								$player_url = TTTC_Public::instance() ? TTTC_Public::instance()->player_url( $player->ID ) : '';
								?>
				<li><span><?php if ( $player_url ) : ?><a
							href="<?php echo esc_url( $player_url ); ?>"><?php echo esc_html( $player->post_title ); ?></a><?php else : ?><?php echo esc_html( $player->post_title ); ?><?php endif; ?></span><strong><?php echo esc_html( absint( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ) ); ?></strong>
				</li>
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
				<li><span><?php if ( $player_url ) : ?><a
							href="<?php echo esc_url( $player_url ); ?>"><?php echo esc_html( $winner['player']->post_title ); ?></a><?php else : ?><?php echo esc_html( $winner['player']->post_title ); ?><?php endif; ?></span><strong><?php echo esc_html( $winner['wins'] ); ?></strong>
				</li>
				<?php endforeach; ?>
			</ol>
			<?php endif; ?>
		</section>
		<section class="tttc-dashboard-section">
			<h2><a
					href="<?php echo esc_url( $tournaments_url ); ?>"><?php esc_html_e( 'Published tournaments', 'table-tennis-tournament-for-clubs' ); ?></a>
			</h2>
			<div class="tttc-stat-list">
				<div>
					<span><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['senior'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['youth'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $tournament_types['both'] ); ?></strong>
				</div>
				<div>
					<span><?php esc_html_e( 'Average players per tournament', 'table-tennis-tournament-for-clubs' ); ?></span><strong><?php echo esc_html( $average_participants ); ?></strong>
				</div>
			</div>
			<h3><?php esc_html_e( 'Tournament statuses', 'table-tennis-tournament-for-clubs' ); ?></h3>
			<div class="tttc-stat-list">
				<?php foreach ( TTTC_Plugin::statuses() as $status_key => $status_label ) : ?>
				<div>
					<span><?php echo esc_html( $status_label ); ?></span><strong><?php echo esc_html( $tournament_statuses[ $status_key ] ); ?></strong>
				</div>
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
		$phone  = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_PHONE, true );
		$active = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_ACTIVE, true );
		$gender = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_GENDER, true );
		$type   = get_post_meta( $post->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
		$gender = $gender ? $gender : 'male';
		$type   = $type ? $type : 'senior';
		$active = '' === $active ? '1' : $active;
		?>
<p><label
		for="tttc-rating"><strong><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input
		class="small-text" type="number" min="0" step="1" id="tttc-rating" name="tttc_rating"
		value="<?php echo esc_attr( $rating ); ?>" required></p>
<p><label
		for="tttc-email"><strong><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input
		class="regular-text" type="email" id="tttc-email" name="tttc_email" value="<?php echo esc_attr( $email ); ?>"></p>
<p><label
		for="tttc-phone"><strong><?php esc_html_e( 'Phone number', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input
		class="regular-text" type="tel" id="tttc-phone" name="tttc_phone" value="<?php echo esc_attr( $phone ); ?>"></p>
<p><label
		for="tttc-gender"><strong><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
		id="tttc-gender" name="tttc_gender">
		<option value="male" <?php selected( $gender, 'male' ); ?>>
			<?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></option>
		<option value="female" <?php selected( $gender, 'female' ); ?>>
			<?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></option>
	</select></p>
<p><label
		for="tttc-type"><strong><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
		id="tttc-type" name="tttc_type">
		<option value="senior" <?php selected( $type, 'senior' ); ?>>
			<?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
		<option value="youth" <?php selected( $type, 'youth' ); ?>>
			<?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
	</select></p>
<p><label><input type="checkbox" name="tttc_active" value="1" <?php checked( '1', $active ); ?>>
		<?php esc_html_e( 'Player is active and available for new tournament assignments', 'table-tennis-tournament-for-clubs' ); ?></label>
</p>
<?php
	}

	public function tournament_meta_box( $post ) {
		wp_nonce_field( 'tttc_save_tournament', 'tttc_tournament_nonce' );
		$date   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$time   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TIME, true );
		$game_formats = TTTC_Plugin::get_tournament_game_formats( $post->ID );
		$games  = (string) $game_formats['preliminary'];
		$intermediate_games = (string) $game_formats['intermediate'];
		$finals_games = (string) $game_formats['finals'];
		$status = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
		$hide_from_overview = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_HIDE_FROM_OVERVIEW, true );
		$type   = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
		$competition_format = TTTC_Plugin::get_tournament_competition_format( $post->ID );
		$description = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_DESCRIPTION, true );
		$phone_required = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_PHONE_REQUIRED, true );
		$hide_ttapp_question = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_HIDE_TTAPP_QUESTION, true );
		$ask_joining = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_ASK_JOINING, true );
		$not_joining_email = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_NOT_JOINING_EMAIL, true );
		$tikkie_enabled = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TIKKIE_ENABLED, true );
		$tikkie_qr = absint( get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TIKKIE_QR, true ) );
		$tikkie_amount = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TIKKIE_AMOUNT, true );
		$tikkie_url = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_TIKKIE_URL, true );
		$tikkie_qr_url = $tikkie_qr ? wp_get_attachment_image_url( $tikkie_qr, 'medium' ) : '';
		$status = $status ? $status : 'draft';
		if ( '' === $type ) {
			$type = get_option( TTTC_Plugin::OPTION_DEFAULT_TYPE, 'both' );
		}
		$type = in_array( $type, array( 'senior', 'youth', 'both' ), true ) ? $type : 'both';
		$games = in_array( (string) $games, array( '1', '3', '5' ), true ) ? (string) $games : '3';
		$intermediate_games = in_array( $intermediate_games, array( '1', '3', '5' ), true ) ? $intermediate_games : $games;
		$finals_games = in_array( $finals_games, array( '1', '3', '5' ), true ) ? $finals_games : $games;

		$format_ranges = TTTC_Plugin::get_format_ranges( $post->ID );
		$has_scores    = TTTC_Plugin::has_scores( $post->ID );
		$no_follow_up_rounds = get_post_meta( $post->ID, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		?>
<?php if ( $has_scores ) : ?>
<div class="notice notice-warning inline" style="margin: 0 0 16px;">
	<p><strong><?php esc_html_e( 'Note:', 'table-tennis-tournament-for-clubs' ); ?></strong>
		<?php esc_html_e( 'Match scores have already been recorded for this tournament. Match formats, group player brackets, and follow-up round settings cannot be changed.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
</div>
<?php endif; ?>
<p><label
		for="tttc-date"><strong><?php esc_html_e( 'Date', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input
		type="date" id="tttc-date" name="tttc_date" value="<?php echo esc_attr( $date ); ?>" required></p>
<p><label
		for="tttc-time"><strong><?php esc_html_e( 'Time', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><input
		type="time" id="tttc-time" name="tttc_time" value="<?php echo esc_attr( $time ); ?>" step="60"></p>
<p><label for="tttc-description"><strong><?php esc_html_e( 'Description', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br>
	<textarea class="large-text" rows="4" id="tttc-description" name="tttc_description"><?php echo esc_textarea( $description ); ?></textarea></p>
<p><label
		for="tttc-games"><strong><?php esc_html_e( 'Preliminary rounds best of', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
		id="tttc-games" name="tttc_games" required <?php disabled( $has_scores ); ?>>
		<option value="1" <?php selected( $games, '1' ); ?>>1</option>
		<option value="3" <?php selected( $games, '3' ); ?>>3</option>
		<option value="5" <?php selected( $games, '5' ); ?>>5</option>
	</select></p>
<p><label for="tttc-intermediate-games"><strong><?php esc_html_e( 'Intermediate rounds best of', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
	id="tttc-intermediate-games" name="tttc_intermediate_games" required <?php disabled( $has_scores ); ?>>
		<option value="1" <?php selected( $intermediate_games, '1' ); ?>>1</option>
		<option value="3" <?php selected( $intermediate_games, '3' ); ?>>3</option>
		<option value="5" <?php selected( $intermediate_games, '5' ); ?>>5</option>
	</select></p>
<p><label for="tttc-finals-games"><strong><?php esc_html_e( 'Finals best of', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
	id="tttc-finals-games" name="tttc_finals_games" required <?php disabled( $has_scores ); ?>>
		<option value="1" <?php selected( $finals_games, '1' ); ?>>1</option>
		<option value="3" <?php selected( $finals_games, '3' ); ?>>3</option>
		<option value="5" <?php selected( $finals_games, '5' ); ?>>5</option>
	</select></p>
<p><label
		for="tttc-type"><strong><?php esc_html_e( 'Player type', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
		id="tttc-type" name="tttc_type">
		<option value="senior" <?php selected( $type, 'senior' ); ?>>
			<?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
		<option value="youth" <?php selected( $type, 'youth' ); ?>>
			<?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
		<option value="both" <?php selected( $type, 'both' ); ?>>
			<?php esc_html_e( 'Both', 'table-tennis-tournament-for-clubs' ); ?></option>
	</select></p>
<p><label for="tttc-competition-format"><strong><?php esc_html_e( 'Competition format', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br>
	<select id="tttc-competition-format" name="tttc_competition_format" <?php disabled( $has_scores ); ?>>
		<option value="standard" <?php selected( $competition_format, 'standard' ); ?>><?php esc_html_e( 'Standard groups and follow-up rounds', 'table-tennis-tournament-for-clubs' ); ?></option>
		<option value="placement_pools" <?php selected( $competition_format, 'placement_pools' ); ?>><?php esc_html_e( 'Groups of 3 → A/B/C placement pools', 'table-tennis-tournament-for-clubs' ); ?></option>
	</select>
	<p class="description"><?php esc_html_e( 'The placement-pool format is available for 9 or more players. Preliminary groups contain 3 or 4 players; first, second, and third place advance to pools A, B, and C. Fourth place in a group of 4 defaults to pool B. Tournament group-size settings also split each A/B/C pool into multiple groups when needed. The No follow-up rounds checkbox controls whether those groups play crossover rounds.', 'table-tennis-tournament-for-clubs' ); ?></p>
</p>
<p><label
		for="tttc-status"><strong><?php esc_html_e( 'Status', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br><select
		id="tttc-status" name="tttc_status">
		<?php foreach ( TTTC_Plugin::statuses() as $key => $label ) : ?>
		<option value="<?php echo esc_attr( $key ); ?>" <?php selected( $status, $key ); ?>>
			<?php echo esc_html( $label ); ?></option>
		<?php endforeach; ?>
	</select></p>

<p><label><input type="checkbox" name="tttc_hide_from_overview" value="1" <?php checked( '1', $hide_from_overview ); ?>>
		<?php esc_html_e( 'Hide this tournament from the public tournament overview', 'table-tennis-tournament-for-clubs' ); ?></label></p>
<p><label><input type="checkbox" name="tttc_phone_required" value="1" <?php checked( '1', $phone_required ); ?>>
		<?php esc_html_e( 'Require a phone number when signing up', 'table-tennis-tournament-for-clubs' ); ?></label></p>
<p><label><input type="checkbox" name="tttc_hide_ttapp_question" value="1" <?php checked( '1', $hide_ttapp_question ); ?>>
		<?php esc_html_e( 'Hide the TTapp rating question when signing up', 'table-tennis-tournament-for-clubs' ); ?></label></p>
<p><label><input type="checkbox" name="tttc_ask_joining" value="1" <?php checked( '1', $ask_joining ); ?>>
		<?php esc_html_e( 'Ask: Are you joining?', 'table-tennis-tournament-for-clubs' ); ?></label></p>
<p><label for="tttc-not-joining-email"><strong><?php esc_html_e( 'Email not joining reply', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br>
	<input type="email" class="regular-text" id="tttc-not-joining-email" name="tttc_not_joining_email" value="<?php echo esc_attr( $not_joining_email ); ?>">
	<span class="description"><?php esc_html_e( 'Notification recipient for people who are not joining.', 'table-tennis-tournament-for-clubs' ); ?></span></p>
<div class="tttc-tikkie-settings">
	<p><label><input type="checkbox" name="tttc_tikkie_enabled" value="1" <?php checked( '1', $tikkie_enabled ); ?>>
			<?php esc_html_e( 'QR-Code Tikkie', 'table-tennis-tournament-for-clubs' ); ?></label></p>
	<p><strong><?php esc_html_e( 'Tikkie QR image', 'table-tennis-tournament-for-clubs' ); ?></strong><br>
		<input type="hidden" id="tttc-tikkie-qr" name="tttc_tikkie_qr" value="<?php echo esc_attr( $tikkie_qr ); ?>">
		<button type="button" class="button tttc-select-tikkie-qr"
			data-title="<?php esc_attr_e( 'Select a QR image', 'table-tennis-tournament-for-clubs' ); ?>"
			data-select="<?php esc_attr_e( 'Use this image', 'table-tennis-tournament-for-clubs' ); ?>"><?php esc_html_e( 'Select QR image', 'table-tennis-tournament-for-clubs' ); ?></button>
		<button type="button" class="button tttc-remove-tikkie-qr" <?php disabled( ! $tikkie_qr ); ?>><?php esc_html_e( 'Remove image', 'table-tennis-tournament-for-clubs' ); ?></button>
		<br><img class="tttc-tikkie-qr-preview" src="<?php echo esc_url( $tikkie_qr_url ? $tikkie_qr_url : '' ); ?>" alt="" style="max-width: 180px; height: auto; margin-top: 8px;<?php echo $tikkie_qr_url ? '' : ' display: none;'; ?>">
	</p>
	<p><label for="tttc-tikkie-amount"><strong><?php esc_html_e( 'Registration fee (EUR)', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br>
		<input type="number" id="tttc-tikkie-amount" name="tttc_tikkie_amount" min="0" step="0.01" value="<?php echo esc_attr( $tikkie_amount ); ?>"></p>
	<p><label for="tttc-tikkie-url"><strong><?php esc_html_e( 'Direct payment link (optional)', 'table-tennis-tournament-for-clubs' ); ?></strong></label><br>
		<input type="url" class="large-text" id="tttc-tikkie-url" name="tttc_tikkie_url" value="<?php echo esc_attr( $tikkie_url ); ?>"></p>
</div>

<div class="tttc-tournament-format-section" style="margin-top: 16px; border-top: 1px solid #dcdcde; padding-top: 12px;">
	<p><strong><?php esc_html_e( 'Group player brackets (format)', 'table-tennis-tournament-for-clubs' ); ?></strong></p>
	<p><label><input type="checkbox" name="tttc_no_follow_up" value="1" <?php checked( '1', $no_follow_up_rounds ); ?> <?php disabled( $has_scores ); ?>>
			<?php esc_html_e( 'No follow-up rounds', 'table-tennis-tournament-for-clubs' ); ?></label></p>
	<p class="description"><?php esc_html_e( 'When enabled, group standings are final and there is no overall winner. When disabled, the configured follow-up rounds are played, including after A/B/C placement pools are split into groups.', 'table-tennis-tournament-for-clubs' ); ?></p>
	<p class="description">
		<?php esc_html_e( 'Customize player count brackets for this tournament or leave default.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
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
						// translators: %d is the number of groups.
						$group_count_label = sprintf( _n( '%d Group', '%d Groups', $g, 'table-tennis-tournament-for-clubs' ), $g );
						?>
			<tr>
				<td><strong><?php echo esc_html( $group_count_label ); ?></strong></td>
				<td><input type="number" class="small-text" min="2" step="1"
						name="tttc_format_ranges[<?php echo esc_attr( $g ); ?>][min]" value="<?php echo esc_attr( $min ); ?>"
						required <?php disabled( $has_scores ); ?>></td>
				<td><input type="number" class="small-text" min="2" step="1"
						name="tttc_format_ranges[<?php echo esc_attr( $g ); ?>][max]" value="<?php echo esc_attr( $max ); ?>"
						required <?php disabled( $has_scores ); ?>></td>
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

		if ( ! isset( $_POST['tttc_player_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_player_nonce'] ) ), 'tttc_save_player' ) ) {
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
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['tttc_player_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_player_nonce'] ) ), 'tttc_save_player' ) ) {
			return;
		}
		$rating = isset( $_POST['tttc_rating'] ) ? max( 0, absint( $_POST['tttc_rating'] ) ) : 0;
		$email  = isset( $_POST['tttc_email'] ) ? sanitize_email( wp_unslash( $_POST['tttc_email'] ) ) : '';
		$phone  = isset( $_POST['tttc_phone'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_phone'] ) ) : '';
		$gender = isset( $_POST['tttc_gender'] ) ? sanitize_key( wp_unslash( $_POST['tttc_gender'] ) ) : 'male';
		$type   = isset( $_POST['tttc_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_type'] ) ) : 'senior';
		$gender = in_array( $gender, array( 'male', 'female' ), true ) ? $gender : 'male';
		$type   = in_array( $type, array( 'senior', 'youth' ), true ) ? $type : 'senior';
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_RATING, $rating );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_EMAIL, $email );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_PHONE, $phone );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_GENDER, $gender );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_TYPE, $type );
		update_post_meta( $post_id, TTTC_Plugin::PLAYER_META_ACTIVE, isset( $_POST['tttc_active'] ) ? '1' : '0' );
	}

	public function save_tournament( $post_id ) {
		if ( ! $this->can_save( $post_id ) ) {
			return;
		}
		if ( ! isset( $_POST['tttc_tournament_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_tournament_nonce'] ) ), 'tttc_save_tournament' ) ) {
			return;
		}
		$date   = isset( $_POST['tttc_date'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_date'] ) ) : '';
		$time   = isset( $_POST['tttc_time'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_time'] ) ) : '';
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
		if ( ! $date_object || $date_object->format( 'Y-m-d' ) !== $date ) {
			$date = '';
		}
		$time_object = '' === $time ? false : DateTime::createFromFormat( 'H:i', $time );
		if ( '' !== $time && ( ! $time_object || $time_object->format( 'H:i' ) !== $time ) ) {
			$time = '';
		}
		$type   = isset( $_POST['tttc_type'] ) ? sanitize_key( wp_unslash( $_POST['tttc_type'] ) ) : 'both';
		$competition_format = isset( $_POST['tttc_competition_format'] ) ? sanitize_key( wp_unslash( $_POST['tttc_competition_format'] ) ) : 'standard';
		$status = isset( $_POST['tttc_status'] ) ? sanitize_key( $_POST['tttc_status'] ) : 'draft';
		if ( ! in_array( $type, array( 'senior', 'youth', 'both' ), true ) ) {
			$type = 'both';
		}
		if ( ! in_array( $competition_format, array( 'standard', 'placement_pools' ), true ) ) {
			$competition_format = 'standard';
		}
		if ( ! array_key_exists( $status, TTTC_Plugin::statuses() ) ) {
			$status = 'draft';
		}
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, $date );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIME, $time );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TYPE, $type );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, $status );
		$hide_from_overview = isset( $_POST['tttc_hide_from_overview'] ) ? '1' : '0';
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_HIDE_FROM_OVERVIEW, $hide_from_overview );
		$description = isset( $_POST['tttc_description'] ) ? sanitize_textarea_field( wp_unslash( $_POST['tttc_description'] ) ) : '';
		$phone_required = isset( $_POST['tttc_phone_required'] ) ? '1' : '0';
		$hide_ttapp_question = isset( $_POST['tttc_hide_ttapp_question'] ) ? '1' : '0';
		$ask_joining = isset( $_POST['tttc_ask_joining'] ) ? '1' : '0';
		$not_joining_email = isset( $_POST['tttc_not_joining_email'] ) ? sanitize_email( wp_unslash( $_POST['tttc_not_joining_email'] ) ) : '';
		$tikkie_enabled = isset( $_POST['tttc_tikkie_enabled'] ) ? '1' : '0';
		$tikkie_qr = isset( $_POST['tttc_tikkie_qr'] ) ? absint( $_POST['tttc_tikkie_qr'] ) : 0;
		if ( ! $tikkie_qr || ! wp_attachment_is_image( $tikkie_qr ) ) {
			$tikkie_qr = 0;
		}
		$tikkie_amount = isset( $_POST['tttc_tikkie_amount'] ) ? str_replace( ',', '.', sanitize_text_field( wp_unslash( $_POST['tttc_tikkie_amount'] ) ) ) : '';
		if ( '' !== $tikkie_amount && ( ! is_numeric( $tikkie_amount ) || (float) $tikkie_amount < 0 ) ) {
			$tikkie_amount = '';
		} elseif ( '' !== $tikkie_amount ) {
			$tikkie_amount = number_format( (float) $tikkie_amount, 2, '.', '' );
		}
		$tikkie_url = isset( $_POST['tttc_tikkie_url'] ) ? esc_url_raw( wp_unslash( $_POST['tttc_tikkie_url'] ) ) : '';
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DESCRIPTION, $description );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_PHONE_REQUIRED, $phone_required );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_HIDE_TTAPP_QUESTION, $hide_ttapp_question );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_ASK_JOINING, $ask_joining );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_NOT_JOINING_EMAIL, $not_joining_email );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIKKIE_ENABLED, $tikkie_enabled );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIKKIE_QR, $tikkie_qr );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIKKIE_AMOUNT, $tikkie_amount );
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIKKIE_URL, $tikkie_url );

		$has_scores = TTTC_Plugin::has_scores( $post_id );
		if ( ! $has_scores ) {
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_COMPETITION_FORMAT, $competition_format );
			$no_follow_up_rounds = isset( $_POST['tttc_no_follow_up'] ) ? '1' : '0';
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, $no_follow_up_rounds );
			$games = isset( $_POST['tttc_games'] ) ? sanitize_key( wp_unslash( $_POST['tttc_games'] ) ) : '3';
			if ( ! in_array( $games, array( '1', '3', '5' ), true ) ) {
				$games = '3';
			}
			$intermediate_games = isset( $_POST['tttc_intermediate_games'] ) ? sanitize_key( wp_unslash( $_POST['tttc_intermediate_games'] ) ) : $games;
			if ( ! in_array( $intermediate_games, array( '1', '3', '5' ), true ) ) {
				$intermediate_games = $games;
			}
			$finals_games = isset( $_POST['tttc_finals_games'] ) ? sanitize_key( wp_unslash( $_POST['tttc_finals_games'] ) ) : $games;
			if ( ! in_array( $finals_games, array( '1', '3', '5' ), true ) ) {
				$finals_games = $games;
			}
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, $games );
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_INTERMEDIATE_GAMES, $intermediate_games );
			update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_FINALS_GAMES, $finals_games );

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

	private function can_save( $post_id ) {
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) ) {
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
	<option value="male" <?php selected( $gender, 'male' ); ?>>
		<?php esc_html_e( 'Male', 'table-tennis-tournament-for-clubs' ); ?></option>
	<option value="female" <?php selected( $gender, 'female' ); ?>>
		<?php esc_html_e( 'Female', 'table-tennis-tournament-for-clubs' ); ?></option>
</select>
<select name="tttc_type">
	<option value=""><?php esc_html_e( 'All types', 'table-tennis-tournament-for-clubs' ); ?></option>
	<option value="senior" <?php selected( $type, 'senior' ); ?>>
		<?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></option>
	<option value="youth" <?php selected( $type, 'youth' ); ?>>
		<?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></option>
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
		$requested_orderby = isset( $_GET['orderby'] ) ? sanitize_key( wp_unslash( $_GET['orderby'] ) ) : '';
		if ( '' === $requested_orderby ) {
			$query->set( 'orderby', 'title' );
			$query->set( 'order', 'ASC' );
		} elseif ( isset( $sortable_meta_keys[ $orderby ] ) ) {
			$query->set( 'meta_key', $sortable_meta_keys[ $orderby ]['key'] );
			$query->set( 'orderby', $sortable_meta_keys[ $orderby ]['orderby'] );
		}
	}

	public function tournament_row_actions( $actions, $post ) {
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== $post->post_type || ! current_user_can( 'edit_post', $post->ID ) ) {
			return $actions;
		}

		$url = wp_nonce_url(
			admin_url( 'admin-post.php?action=tttc_duplicate_tournament&post=' . absint( $post->ID ) ),
			'tttc_duplicate_tournament_' . absint( $post->ID )
		);
		$actions['tttc_duplicate'] = '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Duplicate', 'table-tennis-tournament-for-clubs' ) . '</a>';

		return $actions;
	}

	public function duplicate_tournament() {
		$source_id = isset( $_GET['post'] ) ? absint( wp_unslash( $_GET['post'] ) ) : 0;
		check_admin_referer( 'tttc_duplicate_tournament_' . $source_id );

		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $source_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( ! current_user_can( 'edit_post', $source_id ) ) {
			wp_die( esc_html__( 'You do not have permission to duplicate this tournament.', 'table-tennis-tournament-for-clubs' ) );
		}

		$source = get_post( $source_id );
		if ( ! $source ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		$title = $source->post_title . ' ' . __( 'copy', 'table-tennis-tournament-for-clubs' );
		$copy_id = wp_insert_post(
			array(
				'post_type'   => TTTC_Plugin::TOURNAMENT_POST_TYPE,
				'post_status' => 'draft',
				'post_title'  => $title,
				'post_name'   => $this->unique_tournament_slug( $title ),
			),
			true
		);

		if ( is_wp_error( $copy_id ) ) {
			wp_die( esc_html( $copy_id->get_error_message() ) );
		}

		$meta_keys = array(
			TTTC_Plugin::TOURNAMENT_META_DATE,
			TTTC_Plugin::TOURNAMENT_META_TIME,
			TTTC_Plugin::TOURNAMENT_META_GAMES,
			TTTC_Plugin::TOURNAMENT_META_INTERMEDIATE_GAMES,
			TTTC_Plugin::TOURNAMENT_META_FINALS_GAMES,
			TTTC_Plugin::TOURNAMENT_META_HIDE_FROM_OVERVIEW,
			TTTC_Plugin::TOURNAMENT_META_TYPE,
			TTTC_Plugin::TOURNAMENT_META_COMPETITION_FORMAT,
			TTTC_Plugin::TOURNAMENT_META_FORMAT_RANGES,
			TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP,
			TTTC_Plugin::TOURNAMENT_META_DESCRIPTION,
			TTTC_Plugin::TOURNAMENT_META_PHONE_REQUIRED,
			TTTC_Plugin::TOURNAMENT_META_HIDE_TTAPP_QUESTION,
			TTTC_Plugin::TOURNAMENT_META_ASK_JOINING,
			TTTC_Plugin::TOURNAMENT_META_NOT_JOINING_EMAIL,
		);
		foreach ( $meta_keys as $meta_key ) {
			$meta_value = get_post_meta( $source_id, $meta_key, true );
			if ( '' !== $meta_value ) {
				update_post_meta( $copy_id, $meta_key, $meta_value );
			}
		}
		update_post_meta( $copy_id, TTTC_Plugin::TOURNAMENT_META_STATUS, 'draft' );

		global $wpdb;
		$assignment_table = TTTC_Plugin::table_name();
		$source_players   = $wpdb->get_var( $wpdb->prepare( 'SELECT COUNT(*) FROM %i WHERE tournament_id = %d', $assignment_table, $source_id ) );
		$copied_players   = $wpdb->query(
			$wpdb->prepare(
				'INSERT INTO %i (tournament_id, player_id, rating, seed, created_at)
				SELECT %d, player_id, rating, seed, %s FROM %i WHERE tournament_id = %d',
				$assignment_table,
				$copy_id,
				current_time( 'mysql', true ),
				$assignment_table,
				$source_id
			)
		);

		if ( false === $source_players || false === $copied_players || (int) $source_players !== (int) $copied_players ) {
			wp_delete_post( $copy_id, true );
			wp_die( esc_html__( 'The tournament copy could not be completed because its players could not be copied.', 'table-tennis-tournament-for-clubs' ) );
		}

		wp_safe_redirect( admin_url( 'post.php?post=' . absint( $copy_id ) . '&action=edit' ) );
		exit;
	}

	private function unique_tournament_slug( $title ) {
		global $wpdb;

		$base_slug = sanitize_title( $title );
		$slug      = $base_slug;
		$suffix    = 2;

		while ( $wpdb->get_var( $wpdb->prepare( "SELECT ID FROM {$wpdb->posts} WHERE post_type = %s AND post_name = %s LIMIT 1", TTTC_Plugin::TOURNAMENT_POST_TYPE, $slug ) ) ) {
			$slug = $base_slug . '-' . $suffix;
			++$suffix;
		}

		return $slug;
	}

	public function tournament_columns( $columns ) {
		$status_label = __( 'Status', 'table-tennis-tournament-for-clubs' ) . ' <button type="button" class="tttc-info-trigger dashicons dashicons-info-outline" aria-haspopup="dialog" aria-controls="tttc-status-info-modal" aria-label="' . esc_attr__( 'What can I do in each status?', 'table-tennis-tournament-for-clubs' ) . '"></button>';
		$players_label = __( 'Players', 'table-tennis-tournament-for-clubs' ) . ' <button type="button" class="tttc-info-trigger dashicons dashicons-info-outline" aria-haspopup="dialog" aria-controls="tttc-players-info-modal" aria-label="' . esc_attr__( 'What does the Players column show?', 'table-tennis-tournament-for-clubs' ) . '"></button>';
		return array( 'cb' => $columns['cb'], 'title' => __( 'Name', 'table-tennis-tournament-for-clubs' ), 'tttc_date' => __( 'Date', 'table-tennis-tournament-for-clubs' ), 'tttc_time' => __( 'Time', 'table-tennis-tournament-for-clubs' ), 'tttc_games' => __( 'Preliminary rounds best of', 'table-tennis-tournament-for-clubs' ), 'tttc_status' => $status_label, 'tttc_players' => $players_label, 'tttc_url' => __( 'Url', 'table-tennis-tournament-for-clubs' ), 'tttc_scores' => __( 'Scores', 'table-tennis-tournament-for-clubs' ), 'date' => $columns['date'] );
	}

	public function render_status_info_modal() {
		$screen = get_current_screen();
		if ( ! $screen || TTTC_Plugin::TOURNAMENT_POST_TYPE !== $screen->post_type ) {
			return;
		}

		$rows = array(
			__( 'Draft', 'table-tennis-tournament-for-clubs' ) => __( 'Manage and Order player links are available. No score entry yet.', 'table-tennis-tournament-for-clubs' ),
			__( 'Upcoming', 'table-tennis-tournament-for-clubs' ) => __( 'Manage and Order player links are available. No score entry yet.', 'table-tennis-tournament-for-clubs' ),
			__( 'Active', 'table-tennis-tournament-for-clubs' ) => __( 'Players are locked (no Manage/Order links). The Enter scores link is available.', 'table-tennis-tournament-for-clubs' ),
			__( 'Completed', 'table-tennis-tournament-for-clubs' ) => __( 'Players are locked and score entry is closed.', 'table-tennis-tournament-for-clubs' ),
			__( 'Cancelled', 'table-tennis-tournament-for-clubs' ) => __( 'Manage and Order player links are available. No score entry.', 'table-tennis-tournament-for-clubs' ),
		);
		?>
<div id="tttc-status-info-modal" class="tttc-modal" hidden>
	<div class="tttc-modal-overlay"></div>
	<div class="tttc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tttc-status-info-title">
		<button type="button" class="tttc-modal-close dashicons dashicons-no-alt"
			aria-label="<?php echo esc_attr__( 'Close', 'table-tennis-tournament-for-clubs' ); ?>"></button>
		<h2 id="tttc-status-info-title">
			<?php esc_html_e( 'Status and available actions', 'table-tennis-tournament-for-clubs' ); ?></h2>
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Status', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'What you can do', 'table-tennis-tournament-for-clubs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $rows as $status_label => $description ) : ?>
				<tr>
					<td><?php echo esc_html( $status_label ); ?></td>
					<td><?php echo esc_html( $description ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p class="tttc-modal-note">
			<?php esc_html_e( 'The Website link is shown whenever the tournament date is set, regardless of status.', 'table-tennis-tournament-for-clubs' ); ?>
		</p>
	</div>
</div>
<div id="tttc-players-info-modal" class="tttc-modal" hidden>
	<div class="tttc-modal-overlay"></div>
	<div class="tttc-modal-dialog" role="dialog" aria-modal="true" aria-labelledby="tttc-players-info-title">
		<button type="button" class="tttc-modal-close dashicons dashicons-no-alt"
			aria-label="<?php echo esc_attr__( 'Close', 'table-tennis-tournament-for-clubs' ); ?>"></button>
		<h2 id="tttc-players-info-title">
			<?php esc_html_e( 'Players column explained', 'table-tennis-tournament-for-clubs' ); ?></h2>
		<ul class="tttc-modal-icon-list">
			<li>
				<?php esc_html_e( 'The number shown is how many players are signed up for the tournament.', 'table-tennis-tournament-for-clubs' ); ?>
			</li>
			<li><span class="dashicons dashicons-groups" aria-hidden="true"></span>
				<?php esc_html_e( 'Add or remove players for the tournament.', 'table-tennis-tournament-for-clubs' ); ?>
			</li>
			<li><span class="dashicons dashicons-sort" aria-hidden="true"></span>
				<?php esc_html_e( 'Order the players by strength.', 'table-tennis-tournament-for-clubs' ); ?>
			</li>
		</ul>
	</div>
</div>
<?php
	}

	public function tournament_column( $column, $post_id ) {
		if ( 'tttc_date' === $column ) {
			$date        = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
			$date_object = DateTime::createFromFormat( 'Y-m-d', $date );
			echo esc_html( $date_object ? $date_object->format( 'd-m-Y' ) : $date );
		} elseif ( 'tttc_time' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_TIME, true ) );
		} elseif ( 'tttc_games' === $column ) {
			echo esc_html( get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_GAMES, true ) );
		} elseif ( 'tttc_status' === $column ) {
			$status   = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			$statuses = TTTC_Plugin::statuses();
			if ( ! current_user_can( 'edit_post', $post_id ) ) {
				echo esc_html( isset( $statuses[ $status ] ) ? $statuses[ $status ] : $status );
				return;
			}
			$status = $status ? $status : 'draft';
			echo '<select class="tttc-status-select" aria-label="' . esc_attr__( 'Status', 'table-tennis-tournament-for-clubs' ) . '" data-post-id="' . esc_attr( $post_id ) . '" data-nonce="' . esc_attr( wp_create_nonce( 'tttc_quick_status_' . $post_id ) ) . '">';
			foreach ( $statuses as $key => $label ) {
				echo '<option value="' . esc_attr( $key ) . '"' . selected( $status, $key, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select> <span class="tttc-status-feedback" aria-live="polite"></span>';
		} elseif ( 'tttc_players' === $column ) {
			$count = $this->assigned_player_ids( $post_id );
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			echo esc_html( count( $count ) );
			if ( ! in_array( $status, array( 'active', 'completed' ), true ) ) {
				echo ' <a class="button-link tttc-icon-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-assignments&tournament_id=' . $post_id ) ) . '" title="' . esc_attr__( 'Manage', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-groups" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Manage', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
				echo ' <a class="button-link tttc-icon-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-order&tournament_id=' . $post_id ) ) . '" title="' . esc_attr__( 'Order', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-sort" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Order', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
			}
		} elseif ( 'tttc_url' === $column ) {
			$website_url = $this->tournament_url( $post_id );
			if ( $website_url ) {
				$tv_url = add_query_arg( 'showtv', 'true', $website_url );
				echo '<a class="button-link tttc-icon-link" href="' . esc_url( $website_url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'Website', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-admin-site-alt3" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Website', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
				echo ' <a class="button-link tttc-icon-link" href="' . esc_url( $tv_url ) . '" target="_blank" rel="noopener noreferrer" title="' . esc_attr__( 'TV view', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-desktop" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'TV view', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
			}
		} elseif ( 'tttc_scores' === $column ) {
			$status = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, true );
			if ( 'active' === $status ) {
				echo '<a class="button-link tttc-icon-link" href="' . esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $post_id ) ) . '" title="' . esc_attr__( 'Enter scores', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-edit" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Enter scores', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
				echo ' <a class="button-link tttc-icon-link" href="' . esc_url( add_query_arg( array( 'page' => 'tttc-scores', 'tournament_id' => $post_id, 'print' => 'all' ), admin_url( 'admin.php' ) ) ) . '" title="' . esc_attr__( 'Print all groups', 'table-tennis-tournament-for-clubs' ) . '"><span class="dashicons dashicons-printer" aria-hidden="true"></span><span class="screen-reader-text">' . esc_html__( 'Print all groups', 'table-tennis-tournament-for-clubs' ) . '</span></a>';
			}
		}
	}

	public function ajax_quick_status() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_ajax_referer( 'tttc_quick_status_' . $post_id, 'nonce' );
		if ( ! $post_id || TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			wp_send_json_error( null, 403 );
		}
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		if ( ! array_key_exists( $status, TTTC_Plugin::statuses() ) ) {
			wp_send_json_error( null, 400 );
		}
		update_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_STATUS, $status );

		$cells = array();
		foreach ( array( 'tttc_players', 'tttc_scores' ) as $column ) {
			ob_start();
			$this->tournament_column( $column, $post_id );
			$cells[ $column ] = ob_get_clean();
		}
		wp_send_json_success( $cells );
	}

	private function tournament_url( $post_id ) {
		$date        = get_post_meta( $post_id, TTTC_Plugin::TOURNAMENT_META_DATE, true );
		$date_object = DateTime::createFromFormat( 'Y-m-d', $date );

		return $date_object ? home_url( user_trailingslashit( TTTC_Plugin::get_tournament_url_base() . '/' . get_post_field( 'post_name', $post_id ) . '/' . $date_object->format( 'd-m-Y' ) ) ) : '';
	}

	public function scores_page( $submitted_tournament_id = 0 ) {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}

		$tournament_id = $submitted_tournament_id ? absint( $submitted_tournament_id ) : ( isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0 );
		$print_all_groups = isset( $_GET['print'] ) && is_string( $_GET['print'] ) && 'all' === sanitize_key( wp_unslash( $_GET['print'] ) );
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( 'placement_pools' === TTTC_Plugin::get_tournament_competition_format( $tournament_id ) && count( $this->assigned_player_ids( $tournament_id ) ) >= 9 ) {
			$this->placement_scores_page( $tournament_id );
			return;
		}

		$game_formats  = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$games         = $game_formats['preliminary'];
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
		$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		$competition  = TTTC_Competition::calculate( $schedule, $saved_scores, $game_formats, $no_follow_up_rounds );
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
	<h1>
		<?php echo esc_html( get_the_title( $tournament_id ) . ' - ' . __( 'Scores', 'table-tennis-tournament-for-clubs' ) ); ?>
	</h1>
	<p class="description tttc-scores-help">
		<?php esc_html_e( 'You only need to enter the lowest score of each game; the other field is filled in automatically.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<?php if ( $this->score_error ) : ?><div class="notice notice-error">
		<p><?php echo esc_html( $this->score_error ); ?></p>
	</div><?php endif; ?>
	<?php if ( isset( $_GET['removed'] ) ) : ?><div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'All tournament scores removed.', 'table-tennis-tournament-for-clubs' ); ?></p>
	</div><?php elseif ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Tournament scores updated.', 'table-tennis-tournament-for-clubs' ); ?></p>
	</div><?php endif; ?>
	<?php if ( empty( $schedule ) ) : ?>
	<?php
				// translators: 1: minimum number of players, 2: maximum number of players.
				$player_range_message = sprintf( __( 'A score sheet is available when the tournament has %1$d to %2$d assigned active players.', 'table-tennis-tournament-for-clubs' ), $min_players, $max_players );
				?>
	<p><?php echo esc_html( $player_range_message ); ?></p>
	<?php else : ?>
	<?php $this->render_random_scores_form( $tournament_id ); ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-scores-form<?php echo $print_all_groups ? ' tttc-print-all-groups' : ''; ?>">
		<input type="hidden" name="action" value="tttc_save_scores"><input type="hidden" name="tournament_id"
			value="<?php echo esc_attr( $tournament_id ); ?>">
		<?php wp_nonce_field( 'tttc_save_scores', 'tttc_scores_nonce' ); ?>
		<div class="tttc-scores-toolbar">
			<button type="button" class="button tttc-print-button"
				onclick="window.print(); return false;"><?php esc_html_e( 'Print current group', 'table-tennis-tournament-for-clubs' ); ?></button>
		</div>
		<div class="tttc-public-group-tabs tttc-admin-group-tabs" role="tablist"
			aria-label="<?php esc_attr_e( 'Groups', 'table-tennis-tournament-for-clubs' ); ?>">
			<?php foreach ( $schedule as $group_index => $group_schedule ) :
							$tab_id   = 'tttc-score-group-tab-' . ( $group_index + 1 );
							$panel_id = 'tttc-score-group-panel-' . ( $group_index + 1 );
							// translators: %d is the group number.
							$group_label = sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $group_index + 1 );
							?>
			<button id="<?php echo esc_attr( $tab_id ); ?>"
				class="tttc-admin-group-tab<?php echo 0 === $group_index ? ' is-active' : ''; ?>" type="button" role="tab"
				aria-controls="<?php echo esc_attr( $panel_id ); ?>"
				aria-selected="<?php echo 0 === $group_index ? 'true' : 'false'; ?>"
				tabindex="<?php echo 0 === $group_index ? '0' : '-1'; ?>"><?php echo esc_html( $group_label ); ?></button>
			<?php endforeach; ?>
		</div>
		<?php foreach ( $schedule as $group_index => $group_schedule ) :
						// translators: %d is the group number.
						$group_label = sprintf( __( 'Group %d', 'table-tennis-tournament-for-clubs' ), $group_index + 1 );
						?>
		<section id="<?php echo esc_attr( 'tttc-score-group-panel-' . ( $group_index + 1 ) ); ?>"
			class="tttc-public-group tttc-admin-group<?php echo 0 === $group_index ? ' is-active' : ''; ?>" role="tabpanel"
			aria-labelledby="<?php echo esc_attr( 'tttc-score-group-tab-' . ( $group_index + 1 ) ); ?>"
			<?php echo 0 === $group_index ? '' : ' hidden'; ?>>
			<h2><?php echo esc_html( $group_label ); ?></h2>
			<div class="tttc-public-round-grid">
				<?php foreach ( $group_schedule['rounds'] as $round_number => $round ) :
							// translators: %d is the round number.
							$round_label = sprintf( __( 'Round %d', 'table-tennis-tournament-for-clubs' ), $round_number + 1 );
							?>
				<div class="tttc-public-round"
					id="<?php echo esc_attr( 'tttc-score-group-' . ( $group_index + 1 ) . '-round-' . ( $round_number + 1 ) ); ?>">
					<h3 class="tttc-public-round-title"><?php echo esc_html( $round_label ); ?></h3>
					<div class="tttc-scores-table-wrap">
						<table class="widefat striped tttc-scores-table">
							<thead>
								<tr>
									<th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th>
									<th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th>
									<th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) :
									// translators: %d is the game number.
									$game_label = sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game );
									?><th><?php echo esc_html( $game_label ); ?></th><?php endfor; ?><th class="tttc-print-only"><?php esc_html_e( 'Counter', 'table-tennis-tournament-for-clubs' ); ?></th>
								</tr>
							</thead>
							<tbody>
								<?php $counters = TTTC_Public::instance()->round_counters( $group_schedule['players'], $round ); ?>
								<?php foreach ( $round as $match_number => $match ) : $match_key = $this->match_key( $match[0]->ID, $match[1]->ID ); ?>
								<tr>
									<td data-label="<?php esc_attr_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?>">
										<?php echo esc_html( $match_number + 1 ); ?></td>
									<td data-label="<?php esc_attr_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?>">
										<?php echo esc_html( $match[0]->post_title ); ?></td>
									<td data-label="<?php esc_attr_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?>">
										<?php echo esc_html( $match[1]->post_title ); ?></td>
									<?php for ( $game = 0; $game < $games; $game++ ) :
										// translators: %d is the game number.
										$game_label = sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game + 1 );
										?><td data-label="<?php echo esc_attr( $game_label ); ?>"><span class="tttc-score-pair"><input
												class="small-text" type="number" min="0" inputmode="numeric"
												name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][0]"
												value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][0] ) ? $saved_scores[ $match_key ][ $game ][0] : '' ); ?>"><input
												class="small-text" type="number" min="0" inputmode="numeric"
												name="scores[<?php echo esc_attr( $match_key ); ?>][<?php echo esc_attr( $game ); ?>][1]"
												value="<?php echo esc_attr( isset( $saved_scores[ $match_key ][ $game ][1] ) ? $saved_scores[ $match_key ][ $game ][1] : '' ); ?>"></span>
									</td><?php endfor; ?>
									<td class="tttc-print-only"><?php echo esc_html( $counters[ $match_number ] ); ?></td>
								</tr>
								<?php endforeach; ?>
							</tbody>
						</table>
					</div>
					<p class="tttc-round-save"><button class="button button-primary" name="tttc_return_to"
							value="<?php echo esc_attr( 'tttc-score-group-' . ( $group_index + 1 ) . '-round-' . ( $round_number + 1 ) ); ?>"><?php esc_html_e( 'Save scores', 'table-tennis-tournament-for-clubs' ); ?></button>
					</p>
				</div>
				<?php endforeach; ?>
			</div>
		</section>
		<?php endforeach; ?>
		<?php foreach ( $ordered_stages as $stage_index => $stage ) : $stage_games = $game_formats[ $stage['phase'] ]; ?>
		<section class="tttc-crossover-stage" id="<?php echo esc_attr( 'tttc-score-stage-' . ( $stage_index + 1 ) ); ?>">
			<h2><?php echo esc_html( $stage['label'] ); ?></h2>
			<div class="tttc-scores-table-wrap">
				<table class="widefat striped tttc-scores-table">
					<thead>
						<tr>
							<th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th>
							<th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th>
							<th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $stage_games; $game++ ) :
								// translators: %d is the game number.
								$game_label = sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game );
								?><th><?php echo esc_html( $game_label ); ?></th><?php endfor; ?>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $stage['matches'] as $match_number => $match ) : $available = $match['players'][0] && $match['players'][1]; $score_key = $match['score_key']; ?>
						<tr>
							<td data-label="<?php esc_attr_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?>">
								<?php echo esc_html( $match_number + 1 ); ?></td>
							<td data-label="<?php esc_attr_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?>">
								<?php echo esc_html( $available ? $match['players'][0]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?>
							</td>
							<td data-label="<?php esc_attr_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?>">
								<?php echo esc_html( $available ? $match['players'][1]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?>
							</td><?php for ( $game = 0; $game < $stage_games; $game++ ) :
								// translators: %d is the game number.
								$game_label = sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game + 1 );
								?><td data-label="<?php echo esc_attr( $game_label ); ?>"><span class="tttc-score-pair"><input
										class="small-text" type="number" min="0" inputmode="numeric"
										name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][0]"
										value="<?php echo esc_attr( isset( $saved_scores[ $score_key ][ $game ][0] ) ? $saved_scores[ $score_key ][ $game ][0] : '' ); ?>"
										<?php disabled( ! $available ); ?>><input class="small-text" type="number" min="0"
										inputmode="numeric"
										name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][1]"
										value="<?php echo esc_attr( isset( $saved_scores[ $score_key ][ $game ][1] ) ? $saved_scores[ $score_key ][ $game ][1] : '' ); ?>"
										<?php disabled( ! $available ); ?>></span></td><?php endfor; ?>
						</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			</div>
			<p class="tttc-round-save"><button class="button button-primary" name="tttc_return_to"
					value="<?php echo esc_attr( 'tttc-score-stage-' . ( $stage_index + 1 ) ); ?>"><?php esc_html_e( 'Save scores', 'table-tennis-tournament-for-clubs' ); ?></button>
			</p>
		</section>
		<?php endforeach; ?>
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
		if ( 'placement_pools' === TTTC_Plugin::get_tournament_competition_format( $tournament_id ) && count( $this->assigned_player_ids( $tournament_id ) ) >= 9 ) {
			$this->save_placement_scores( $tournament_id );
			return;
		}

		$game_formats  = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$games         = $game_formats['preliminary'];
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
								'scores'  => $this->form_scores( $submitted, $schedule, $game_formats ),
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
		$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		$competition = TTTC_Competition::calculate( $schedule, $validated, $game_formats, $no_follow_up_rounds );
		foreach ( $competition['stages'] as $stage ) {
			foreach ( $stage['matches'] as $match ) {
				if ( ! $match['players'][0] || ! $match['players'][1] ) {
					continue;
				}
				$match_scores = isset( $submitted[ $match['score_key'] ] ) && is_array( $submitted[ $match['score_key'] ] ) ? $submitted[ $match['score_key'] ] : array();
				$match_result = $this->validate_match_scores( $match_scores, $game_formats[ $match['phase'] ] );
				if ( is_wp_error( $match_result ) ) {
					set_transient( $this->score_error_transient_key( $tournament_id ), array( 'message' => $match_result->get_error_message(), 'scores' => $this->form_scores( $submitted, $schedule, $game_formats ) ), MINUTE_IN_SECONDS );
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

		$return_to = isset( $_POST['tttc_return_to'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_return_to'] ) ) : '';
		$anchor    = preg_match( '/^tttc-score-[a-z0-9-]+$/', $return_to ) ? '#' . $return_to : '';
		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&updated=1' . $anchor ) );
		exit;
	}

	private function render_random_scores_form( $tournament_id, $group = '', $round = null, $phase = '' ) {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-random-scores-form" data-confirm="<?php echo esc_attr__( 'This will replace existing scores with random results. Continue?', 'table-tennis-tournament-for-clubs' ); ?>">
			<input type="hidden" name="action" value="tttc_random_scores">
			<input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
			<input type="hidden" name="tttc_random_group" value="<?php echo esc_attr( $group ); ?>">
			<input type="hidden" name="tttc_random_round" value="<?php echo esc_attr( null === $round ? '' : $round ); ?>">
			<input type="hidden" name="tttc_random_phase" value="<?php echo esc_attr( $phase ); ?>">
			<?php wp_nonce_field( 'tttc_random_scores_' . $tournament_id, 'tttc_random_scores_nonce' ); ?>
			<p><button type="submit" class="button"><?php esc_html_e( 'Fill all scores with random results', 'table-tennis-tournament-for-clubs' ); ?></button></p>
		</form>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-remove-scores-form" data-confirm="<?php echo esc_attr__( 'This will permanently remove all scores and reset confirmed placement-pool assignments so preliminary scores can be entered again. Continue?', 'table-tennis-tournament-for-clubs' ); ?>">
			<input type="hidden" name="action" value="tttc_remove_scores">
			<input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
			<input type="hidden" name="tttc_random_group" value="<?php echo esc_attr( $group ); ?>">
			<input type="hidden" name="tttc_random_round" value="<?php echo esc_attr( null === $round ? '' : $round ); ?>">
			<input type="hidden" name="tttc_random_phase" value="<?php echo esc_attr( $phase ); ?>">
			<?php wp_nonce_field( 'tttc_remove_scores_' . $tournament_id, 'tttc_remove_scores_nonce' ); ?>
			<p><button type="submit" class="button"><?php esc_html_e( 'Remove all scores', 'table-tennis-tournament-for-clubs' ); ?></button></p>
		</form>
		<?php
	}

	public function random_scores() {
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['tttc_random_scores_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_random_scores_nonce'] ) ), 'tttc_random_scores_' . $tournament_id ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		$game_formats = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$is_placement_format = 'placement_pools' === TTTC_Plugin::get_tournament_competition_format( $tournament_id ) && count( $this->assigned_player_ids( $tournament_id ) ) >= 9;
		$pools = $is_placement_format ? get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true ) : false;
		$randomized_placement_pools = $is_placement_format && is_array( $pools );
		$records = array();

		if ( $randomized_placement_pools ) {
			$schedule = TTTC_Public::instance()->placement_pool_schedule( $tournament_id, $pools );
			if ( empty( $schedule ) ) {
				wp_die( esc_html__( 'No placement-pool matches are available to score.', 'table-tennis-tournament-for-clubs' ) );
			}
			foreach ( $schedule as $group_key => $group_schedule ) {
				foreach ( $group_schedule['rounds'] as $round_number => $round ) {
					foreach ( $round as $match_number => $match ) {
						$score_key = TTTC_Plugin::placement_score_key( 'placement', $group_schedule['score_group'], $match[0]->ID, $match[1]->ID );
						$records[ $score_key ] = $this->random_score_record( $match, $round_number + 1, $match_number + 1, $game_formats['intermediate'] );
					}
				}
			}
			$placement_scores = array();
			foreach ( $records as $score_key => $record ) {
				$placement_scores[ $score_key ] = $record['scores'];
			}
			$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
			$stage_index = 0;
			while ( true ) {
				$pool_competitions = TTTC_Public::instance()->placement_pool_competitions( $tournament_id, $schedule, $placement_scores, $game_formats, $no_follow_up_rounds );
				$has_stage = false;
				foreach ( $pool_competitions as $pool_result ) {
					if ( ! isset( $pool_result['competition']['stages'][ $stage_index ] ) ) {
						continue;
					}
					$has_stage = true;
					$stage = $pool_result['competition']['stages'][ $stage_index ];
					foreach ( $stage['matches'] as $match_number => $match ) {
						if ( ! $match['players'][0] || ! $match['players'][1] ) {
							continue;
						}
						$score_key = $match['score_key'];
						$records[ $score_key ] = $this->random_score_record( $match['players'], 0, $match_number + 1, $game_formats[ $match['phase'] ] );
						$placement_scores[ $score_key ] = $records[ $score_key ]['scores'];
					}
				}
				if ( ! $has_stage ) {
					break;
				}
				$stage_index++;
			}
		} elseif ( $is_placement_format ) {
			$schedule = TTTC_Public::instance()->tournament_schedule( $tournament_id );
			if ( empty( $schedule ) ) {
				wp_die( esc_html__( 'No preliminary matches are available to score.', 'table-tennis-tournament-for-clubs' ) );
			}
			foreach ( $schedule as $group_schedule ) {
				foreach ( $group_schedule['rounds'] as $round_number => $round ) {
					foreach ( $round as $match_number => $match ) {
						$score_key = $this->match_key( $match[0]->ID, $match[1]->ID );
						$records[ $score_key ] = $this->random_score_record( $match, $round_number + 1, $match_number + 1, $game_formats['preliminary'] );
					}
				}
			}
		} else {
			$schedule = TTTC_Public::instance()->tournament_schedule( $tournament_id );
			if ( empty( $schedule ) ) {
				wp_die( esc_html__( 'No matches are available to score.', 'table-tennis-tournament-for-clubs' ) );
			}
			$scores = array();
			foreach ( $schedule as $group_schedule ) {
				foreach ( $group_schedule['rounds'] as $round_number => $round ) {
					foreach ( $round as $match_number => $match ) {
						$score_key = $this->match_key( $match[0]->ID, $match[1]->ID );
						$records[ $score_key ] = $this->random_score_record( $match, $round_number + 1, $match_number + 1, $game_formats['preliminary'] );
						$scores[ $score_key ] = $records[ $score_key ]['scores'];
					}
				}
			}

			$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
			$stage_index = 0;
			while ( true ) {
				$competition = TTTC_Competition::calculate( $schedule, $scores, $game_formats, $no_follow_up_rounds );
				if ( ! isset( $competition['stages'][ $stage_index ] ) ) {
					break;
				}
				$stage = $competition['stages'][ $stage_index ];
				foreach ( $stage['matches'] as $match_number => $match ) {
					if ( ! $match['players'][0] || ! $match['players'][1] ) {
						continue;
					}
					$score_key = $match['score_key'];
					$records[ $score_key ] = $this->random_score_record( $match['players'], 0, $match_number + 1, $game_formats[ $match['phase'] ] );
					$scores[ $score_key ] = $records[ $score_key ]['scores'];
				}
				$stage_index++;
			}
		}

		$this->persist_random_score_records( $tournament_id, $records, $randomized_placement_pools );
		$redirect_args = array(
			'page'          => 'tttc-scores',
			'tournament_id' => $tournament_id,
			'updated'       => 1,
		);
		if ( $randomized_placement_pools ) {
			$redirect_args['phase'] = 'placement';
			if ( isset( $_POST['tttc_random_group'] ) && is_scalar( $_POST['tttc_random_group'] ) ) {
				$redirect_args['group'] = sanitize_text_field( wp_unslash( $_POST['tttc_random_group'] ) );
			}
			if ( isset( $_POST['tttc_random_round'] ) && is_scalar( $_POST['tttc_random_round'] ) ) {
				$redirect_args['round'] = absint( $_POST['tttc_random_round'] );
			}
		}
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	public function remove_scores() {
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( ! current_user_can( 'manage_options' ) || ! isset( $_POST['tttc_remove_scores_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_remove_scores_nonce'] ) ), 'tttc_remove_scores_' . $tournament_id ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		global $wpdb;
		$deleted = $wpdb->delete( TTTC_Plugin::scores_table_name(), array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		if ( false === $deleted ) {
			wp_die( esc_html__( 'Tournament scores could not be removed.', 'table-tennis-tournament-for-clubs' ) );
		}

		$had_placement_pools = is_array( get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true ) );
		delete_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS );

		$redirect_args = array(
			'page'          => 'tttc-scores',
			'tournament_id' => $tournament_id,
			'removed'       => 1,
		);
		$phase = isset( $_POST['tttc_random_phase'] ) && is_scalar( $_POST['tttc_random_phase'] ) ? sanitize_key( wp_unslash( $_POST['tttc_random_phase'] ) ) : '';
		if ( $had_placement_pools ) {
			$redirect_args['phase'] = 'preliminary';
		}
		$group = isset( $_POST['tttc_random_group'] ) && is_scalar( $_POST['tttc_random_group'] ) ? sanitize_text_field( wp_unslash( $_POST['tttc_random_group'] ) ) : '';
		if ( '' !== $group && ( ! $had_placement_pools || 'preliminary' === $phase ) ) {
			$redirect_args['group'] = $group;
		}
		if ( ! $had_placement_pools && isset( $_POST['tttc_random_round'] ) && is_scalar( $_POST['tttc_random_round'] ) && '' !== (string) wp_unslash( $_POST['tttc_random_round'] ) ) {
			$redirect_args['round'] = absint( wp_unslash( $_POST['tttc_random_round'] ) );
		}
		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	private function random_score_record( $players, $round_number, $match_number, $games ) {
		$scores = array();
		$wins = array( 0, 0 );
		$required_wins = (int) ceil( $games / 2 );
		while ( max( $wins ) < $required_wins ) {
			$winner = wp_rand( 0, 1 );
			if ( 0 === wp_rand( 0, 4 ) ) {
				$loser_score = wp_rand( 10, 15 );
				$winner_score = $loser_score + 2;
			} else {
				$loser_score = wp_rand( 0, 9 );
				$winner_score = 11;
			}
			$scores[] = 0 === $winner
				? array( (string) $winner_score, (string) $loser_score )
				: array( (string) $loser_score, (string) $winner_score );
			$wins[ $winner ]++;
		}

		$validated_scores = $this->validate_match_scores( $scores, $games );
		if ( is_wp_error( $validated_scores ) ) {
			wp_die( esc_html( $validated_scores->get_error_message() ) );
		}
		$player_ids = array( (int) $players[0]->ID, (int) $players[1]->ID );
		sort( $player_ids, SORT_NUMERIC );

		return array(
			'player_one_id' => $player_ids[0],
			'player_two_id' => $player_ids[1],
			'round_number'  => $round_number,
			'match_number'  => $match_number,
			'scores'        => $validated_scores,
		);
	}

	private function persist_random_score_records( $tournament_id, $records, $placement_only ) {
		global $wpdb;
		$table = TTTC_Plugin::scores_table_name();
		if ( $placement_only ) {
			$deleted = $wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE tournament_id = %d AND match_key LIKE %s', $table, $tournament_id, 'p%' ) );
		} else {
			$deleted = $wpdb->delete( $table, array( 'tournament_id' => $tournament_id ), array( '%d' ) );
		}
		if ( false === $deleted ) {
			wp_die( esc_html__( 'The random scores could not be saved.', 'table-tennis-tournament-for-clubs' ) );
		}

		foreach ( $records as $score_key => $record ) {
			$inserted = $wpdb->insert(
				$table,
				array(
					'tournament_id' => $tournament_id,
					'match_key'     => $score_key,
					'player_one_id' => $record['player_one_id'],
					'player_two_id' => $record['player_two_id'],
					'round_number'  => $record['round_number'],
					'match_number'  => $record['match_number'],
					'scores'        => wp_json_encode( $record['scores'] ),
					'updated_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				wp_die( esc_html__( 'The random scores could not be saved.', 'table-tennis-tournament-for-clubs' ) );
			}
		}
	}

	private function save_placement_scores( $tournament_id ) {
		$phase = isset( $_POST['tttc_placement_phase'] ) ? sanitize_key( wp_unslash( $_POST['tttc_placement_phase'] ) ) : '';
		if ( ! in_array( $phase, array( 'preliminary', 'placement' ), true ) ) {
			wp_die( esc_html__( 'The competition phase is invalid.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( 'placement' === $phase && isset( $_POST['tttc_placement_stage_pool'], $_POST['tttc_placement_stage_index'] ) ) {
			$this->save_placement_stage_scores( $tournament_id );
			return;
		}
		$pools = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true );
		if ( 'placement' === $phase && ! is_array( $pools ) ) {
			wp_die( esc_html__( 'Review and save the A/B/C placement pools before entering placement scores.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( 'preliminary' === $phase && is_array( $pools ) ) {
			wp_die( esc_html__( 'Preliminary scores are locked after placement pools have been confirmed.', 'table-tennis-tournament-for-clubs' ) );
		}

		$public = TTTC_Public::instance();
		$schedule = 'preliminary' === $phase ? $public->tournament_schedule( $tournament_id ) : $public->placement_pool_schedule( $tournament_id, $pools );
		$group_key = isset( $_POST['tttc_placement_group'] ) && is_scalar( $_POST['tttc_placement_group'] ) ? sanitize_text_field( (string) wp_unslash( $_POST['tttc_placement_group'] ) ) : '';
		if ( ! array_key_exists( $group_key, $schedule ) ) {
			wp_die( esc_html__( 'The selected competition group could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( isset( $_POST['tttc_placement_round'] ) && ! is_scalar( $_POST['tttc_placement_round'] ) ) {
			wp_die( esc_html__( 'The selected competition round could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		$submitted_round = isset( $_POST['tttc_placement_round'] ) ? (string) wp_unslash( $_POST['tttc_placement_round'] ) : '0';
		if ( '' === $submitted_round ) {
			$selected_rounds = $schedule[ $group_key ]['rounds'];
			$round_number = array_key_first( $selected_rounds );
			if ( null === $round_number ) {
				wp_die( esc_html__( 'The selected competition round could not be found.', 'table-tennis-tournament-for-clubs' ) );
			}
		} else {
			$round_number = absint( $submitted_round );
			if ( ! isset( $schedule[ $group_key ]['rounds'][ $round_number ] ) ) {
				wp_die( esc_html__( 'The selected competition round could not be found.', 'table-tennis-tournament-for-clubs' ) );
			}
			$selected_rounds = array( $round_number => $schedule[ $group_key ]['rounds'][ $round_number ] );
		}
		$selected_schedule = array(
			$group_key => array_merge(
				$schedule[ $group_key ],
				array( 'rounds' => $selected_rounds )
			),
		);
		$game_formats = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$games = 'preliminary' === $phase ? $game_formats['preliminary'] : $game_formats['intermediate'];
		$submitted = isset( $_POST['scores'] ) && is_array( $_POST['scores'] ) ? wp_unslash( $_POST['scores'] ) : array();
		$validated = array();
		foreach ( $selected_schedule as $group_key => $group_schedule ) {
			foreach ( $group_schedule['rounds'] as $round_number => $round ) {
				foreach ( $round as $match_index => $match ) {
					$score_key = TTTC_Plugin::placement_score_key( $phase, 'placement' === $phase ? $group_schedule['score_group'] : '', $match[0]->ID, $match[1]->ID );
					$match_scores = isset( $submitted[ $score_key ] ) && is_array( $submitted[ $score_key ] ) ? $submitted[ $score_key ] : array();
					$result = $this->validate_match_scores( $match_scores, $games );
					if ( is_wp_error( $result ) ) {
						set_transient(
							'tttc_placement_score_error_' . get_current_user_id() . '_' . absint( $tournament_id ),
							array( 'message' => $result->get_error_message(), 'scores' => $this->sanitize_placement_score_input( $submitted ) ),
							MINUTE_IN_SECONDS
						);
						wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=' . $phase . '&group=' . rawurlencode( (string) $group_key ) . '&round=' . $round_number . '&error=1' ) );
						exit;
					}
					$validated[ $score_key ] = array(
						'scores'      => $result,
						'players'     => $match,
						'round'       => $round_number + 1,
						'match'       => $match_index + 1,
					);
				}
			}
		}

		global $wpdb;
		$table = TTTC_Plugin::scores_table_name();
		foreach ( $validated as $score_key => $match ) {
			$player_ids = array( (int) $match['players'][0]->ID, (int) $match['players'][1]->ID );
			sort( $player_ids, SORT_NUMERIC );
			$deleted = $wpdb->delete( $table, array( 'tournament_id' => $tournament_id, 'match_key' => $score_key ), array( '%d', '%s' ) );
			if ( false === $deleted ) {
				wp_die( esc_html__( 'The placement-round scores could not be saved.', 'table-tennis-tournament-for-clubs' ) );
			}
			$inserted = $wpdb->insert(
				$table,
				array(
					'tournament_id' => $tournament_id,
					'match_key'     => $score_key,
					'player_one_id' => $player_ids[0],
					'player_two_id' => $player_ids[1],
					'round_number'  => $match['round'],
					'match_number'  => $match['match'],
					'scores'        => wp_json_encode( $match['scores'] ),
					'updated_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				wp_die( esc_html__( 'The placement-group scores could not be saved.', 'table-tennis-tournament-for-clubs' ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=' . $phase . '&group=' . rawurlencode( (string) $group_key ) . '&round=' . $round_number . '&updated=1' ) );
		exit;
	}

	private function save_placement_stage_scores( $tournament_id ) {
		$pool = isset( $_POST['tttc_placement_stage_pool'] ) && is_scalar( $_POST['tttc_placement_stage_pool'] )
			? strtoupper( sanitize_key( wp_unslash( $_POST['tttc_placement_stage_pool'] ) ) )
			: '';
		$stage_index = isset( $_POST['tttc_placement_stage_index'] ) && is_scalar( $_POST['tttc_placement_stage_index'] )
			? absint( wp_unslash( $_POST['tttc_placement_stage_index'] ) )
			: -1;
		$public = TTTC_Public::instance();
		$pools = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true );
		$schedule = $public->placement_pool_schedule( $tournament_id, $pools );
		$game_formats = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$saved_scores = $this->saved_scores( $tournament_id );
		$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		$competitions = $public->placement_pool_competitions( $tournament_id, $schedule, $saved_scores, $game_formats, $no_follow_up_rounds );
		if ( ! isset( $competitions[ $pool ]['competition']['stages'][ $stage_index ] ) ) {
			wp_die( esc_html__( 'The selected crossover round could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}

		$stage = $competitions[ $pool ]['competition']['stages'][ $stage_index ];
		$submitted = isset( $_POST['scores'] ) && is_array( $_POST['scores'] ) ? wp_unslash( $_POST['scores'] ) : array();
		$validated = array();
		foreach ( $stage['matches'] as $match ) {
			if ( ! $match['players'][0] || ! $match['players'][1] ) {
				continue;
			}
			$score_key = $match['score_key'];
			$match_scores = isset( $submitted[ $score_key ] ) && is_array( $submitted[ $score_key ] ) ? $submitted[ $score_key ] : array();
			$result = $this->validate_match_scores( $match_scores, $game_formats[ $stage['phase'] ] );
			if ( is_wp_error( $result ) ) {
				set_transient(
					'tttc_placement_score_error_' . get_current_user_id() . '_' . absint( $tournament_id ),
					array( 'message' => $result->get_error_message(), 'scores' => $this->sanitize_placement_score_input( $submitted ) ),
					MINUTE_IN_SECONDS
				);
				wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=placement&stage_pool=' . rawurlencode( $pool ) . '&stage=' . $stage_index . '&error=1' ) );
				exit;
			}
			$validated[ $score_key ] = array( 'scores' => $result, 'players' => $match['players'] );
		}

		global $wpdb;
		$table = TTTC_Plugin::scores_table_name();
		foreach ( $validated as $score_key => $match ) {
			$player_ids = array( (int) $match['players'][0]->ID, (int) $match['players'][1]->ID );
			sort( $player_ids, SORT_NUMERIC );
			$wpdb->delete( $table, array( 'tournament_id' => $tournament_id, 'match_key' => $score_key ), array( '%d', '%s' ) );
			$inserted = $wpdb->insert(
				$table,
				array(
					'tournament_id' => $tournament_id,
					'match_key'     => $score_key,
					'player_one_id' => $player_ids[0],
					'player_two_id' => $player_ids[1],
					'round_number'  => 0,
					'match_number'  => 1,
					'scores'        => wp_json_encode( $match['scores'] ),
					'updated_at'    => current_time( 'mysql', true ),
				),
				array( '%d', '%s', '%d', '%d', '%d', '%d', '%s', '%s' )
			);
			if ( false === $inserted ) {
				wp_die( esc_html__( 'The placement-round scores could not be saved.', 'table-tennis-tournament-for-clubs' ) );
			}
		}

		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=placement&stage_pool=' . rawurlencode( $pool ) . '&stage=' . $stage_index . '&updated=1' ) );
		exit;
	}

	private function sanitize_placement_score_input( $submitted ) {
		$sanitized = array();
		foreach ( $submitted as $score_key => $games ) {
			if ( ! is_scalar( $score_key ) || ! is_array( $games ) ) {
				continue;
			}
			$score_key = sanitize_text_field( (string) $score_key );
			foreach ( $games as $game_number => $game_score ) {
				if ( ! is_array( $game_score ) ) {
					continue;
				}
				$sanitized[ $score_key ][ absint( $game_number ) ] = array(
					isset( $game_score[0] ) && is_scalar( $game_score[0] ) ? sanitize_text_field( (string) $game_score[0] ) : '',
					isset( $game_score[1] ) && is_scalar( $game_score[1] ) ? sanitize_text_field( (string) $game_score[1] ) : '',
				);
			}
		}

		return $sanitized;
	}

	private function placement_scores_page( $tournament_id ) {
		$all_scores = $this->saved_scores( $tournament_id );
		$pools = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true );
		$print_all_groups = isset( $_GET['print'] ) && is_string( $_GET['print'] ) && 'all' === sanitize_key( wp_unslash( $_GET['print'] ) );
		$phase = isset( $_GET['phase'] ) && is_string( $_GET['phase'] ) ? sanitize_key( wp_unslash( $_GET['phase'] ) ) : '';
		if ( ! in_array( $phase, array( 'preliminary', 'placement' ), true ) ) {
			$phase = is_array( $pools ) ? 'placement' : 'preliminary';
		}
		if ( 'placement' === $phase && ! is_array( $pools ) ) {
			$phase = 'preliminary';
		}

		$public = TTTC_Public::instance();
		$schedule = 'preliminary' === $phase ? $public->tournament_schedule( $tournament_id ) : $public->placement_pool_schedule( $tournament_id, $pools );
		$game_formats = TTTC_Plugin::get_tournament_game_formats( $tournament_id );
		$games = 'preliminary' === $phase ? $game_formats['preliminary'] : $game_formats['intermediate'];
		$phase_scores = TTTC_Plugin::group_scores( $schedule, $all_scores, $phase, '' );
		$competition = TTTC_Competition::calculate( $schedule, $phase_scores, $games, true );
		$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		$pool_competitions = 'placement' === $phase
			? $public->placement_pool_competitions( $tournament_id, $schedule, $all_scores, $game_formats, $no_follow_up_rounds )
			: array();
		$has_placement_stages = false;
		foreach ( $pool_competitions as $pool_result ) {
			if ( ! empty( $pool_result['competition']['stages'] ) ) {
				$has_placement_stages = true;
				break;
			}
		}
		$error_key = 'tttc_placement_score_error_' . get_current_user_id() . '_' . absint( $tournament_id );
		$error = get_transient( $error_key );
		if ( false !== $error ) {
			delete_transient( $error_key );
		}
		if ( is_array( $error ) && isset( $error['scores'] ) && is_array( $error['scores'] ) ) {
			$all_scores = array_merge( $all_scores, $error['scores'] );
		}
		$error_message = is_array( $error ) && isset( $error['message'] ) ? $error['message'] : ( is_string( $error ) ? $error : '' );
		$all_complete = ! empty( $schedule );
		foreach ( $competition['groups'] as $group ) {
			if ( empty( $group['complete'] ) ) {
				$all_complete = false;
				break;
			}
		}
		$selected_group = isset( $_GET['group'] ) && is_scalar( $_GET['group'] ) ? sanitize_text_field( (string) wp_unslash( $_GET['group'] ) ) : '';
		if ( ! array_key_exists( $selected_group, $schedule ) ) {
			$selected_group = array_key_first( $schedule );
		}
		$selected_round = null;
		$visible_schedule = array();
		if ( null !== $selected_group ) {
			$rounds = $schedule[ $selected_group ]['rounds'];
			if ( ! empty( $rounds ) ) {
				$selected_round = isset( $_GET['round'] ) && is_scalar( $_GET['round'] ) ? absint( $_GET['round'] ) : 0;
				if ( ! isset( $rounds[ $selected_round ] ) ) {
					$selected_round = 0;
				}
			}
			$visible_schedule = array(
				$selected_group => array_merge(
					$schedule[ $selected_group ],
					array( 'rounds' => $rounds )
				),
			);
		}
		if ( $print_all_groups ) {
			$visible_schedule = $schedule;
		}
		$selected_stage_pool = isset( $_GET['stage_pool'] ) && is_scalar( $_GET['stage_pool'] ) ? strtoupper( sanitize_key( wp_unslash( $_GET['stage_pool'] ) ) ) : '';
		$selected_stage_index = isset( $_GET['stage'] ) && is_scalar( $_GET['stage'] ) ? absint( $_GET['stage'] ) : null;
		$selected_stage = null;
		if ( 'placement' === $phase && null !== $selected_stage_index && isset( $pool_competitions[ $selected_stage_pool ]['competition']['stages'][ $selected_stage_index ] ) ) {
			$selected_stage = $pool_competitions[ $selected_stage_pool ]['competition']['stages'][ $selected_stage_index ];
		}
		?>
<div class="wrap">
	<h1><?php echo esc_html( get_the_title( $tournament_id ) . ' - ' . __( 'Scores', 'table-tennis-tournament-for-clubs' ) ); ?></h1>
	<p class="description tttc-scores-help"><?php esc_html_e( 'Enter the lowest score of each game; the other field is filled in automatically.', 'table-tennis-tournament-for-clubs' ); ?></p>
	<?php if ( '' !== $error_message ) : ?><div class="notice notice-error"><p><?php echo esc_html( $error_message ); ?></p></div><?php endif; ?>
	<?php if ( isset( $_GET['removed'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'All tournament scores removed.', 'table-tennis-tournament-for-clubs' ); ?></p></div><?php elseif ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible"><p><?php esc_html_e( 'Tournament scores updated.', 'table-tennis-tournament-for-clubs' ); ?></p></div><?php endif; ?>
	<nav class="nav-tab-wrapper">
		<a class="nav-tab <?php echo 'preliminary' === $phase ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=preliminary' ) ); ?>"><?php esc_html_e( 'Preliminary groups', 'table-tennis-tournament-for-clubs' ); ?></a>
		<?php if ( is_array( $pools ) ) : ?><a class="nav-tab <?php echo 'placement' === $phase ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=placement' ) ); ?>"><?php esc_html_e( 'A/B/C placement pools', 'table-tennis-tournament-for-clubs' ); ?></a><?php endif; ?>
	</nav>
	<?php if ( empty( $schedule ) ) : ?>
		<p><?php esc_html_e( 'The placement-pool format requires at least 9 assigned active players.', 'table-tennis-tournament-for-clubs' ); ?></p>
	<?php else : ?>
		<?php $this->render_random_scores_form( $tournament_id, $selected_group, $selected_round, $phase ); ?>
		<?php if ( 'preliminary' === $phase && $all_complete && ! is_array( $pools ) ) : ?>
			<div class="notice notice-success inline"><p><?php esc_html_e( 'All preliminary matches are complete. Review the suggested A/B/C placement pools before starting the next round.', 'table-tennis-tournament-for-clubs' ); ?> <a class="button button-primary" href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-placement-pools&tournament_id=' . $tournament_id ) ); ?>"><?php esc_html_e( 'Review placement pools', 'table-tennis-tournament-for-clubs' ); ?></a></p></div>
		<?php endif; ?>
		<?php if ( 'placement' === $phase && $all_complete && ! $has_placement_stages ) : ?>
			<?php foreach ( $competition['groups'] as $index => $group ) : ?>
				<h2><?php echo esc_html( $schedule[ array_keys( $schedule )[ $index ] ]['label'] ); ?> — <?php esc_html_e( 'Final places', 'table-tennis-tournament-for-clubs' ); ?></h2>
				<ol><?php foreach ( array_slice( $group['standings'], 0, 3 ) as $standing ) : ?><li><?php echo esc_html( $standing['player']->post_title ); ?></li><?php endforeach; ?></ol>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( 'placement' === $phase && $has_placement_stages ) : ?>
			<?php foreach ( $pool_competitions as $pool => $pool_result ) : ?>
				<?php if ( ! empty( $pool_result['competition']['places'] ) ) : ?>
					<h2><?php echo esc_html( sprintf( __( 'Pool %s final places', 'table-tennis-tournament-for-clubs' ), $pool ) ); ?></h2>
					<ol><?php foreach ( array_slice( $pool_result['competition']['places'], 0, 3 ) as $place ) : ?><li><?php echo esc_html( $place['player']->post_title ); ?></li><?php endforeach; ?></ol>
				<?php endif; ?>
			<?php endforeach; ?>
		<?php endif; ?>
		<?php if ( 'placement' === $phase ) : ?>
			<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-placement-pools&tournament_id=' . $tournament_id ) ); ?>"><?php esc_html_e( 'Review A/B/C pool assignments', 'table-tennis-tournament-for-clubs' ); ?></a></p>
		<?php endif; ?>
		<nav class="nav-tab-wrapper tttc-admin-group-tabs" aria-label="<?php esc_attr_e( 'Competition groups', 'table-tennis-tournament-for-clubs' ); ?>">
			<?php foreach ( $schedule as $group_key => $group_schedule ) :
				$group_url = admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=' . $phase . '&group=' . rawurlencode( (string) $group_key ) . '&round=0' );
				?>
				<a class="nav-tab <?php echo null === $selected_stage && (string) $selected_group === (string) $group_key ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $group_url ); ?>"><?php echo esc_html( $group_schedule['label'] ); ?></a>
			<?php endforeach; ?>
		</nav>
		<?php if ( 'placement' === $phase && ! $no_follow_up_rounds ) : ?>
			<nav class="nav-tab-wrapper tttc-admin-group-tabs" aria-label="<?php esc_attr_e( 'Crossover rounds', 'table-tennis-tournament-for-clubs' ); ?>">
				<?php foreach ( $pool_competitions as $pool => $pool_result ) : ?>
					<?php foreach ( $pool_result['competition']['stages'] as $stage_index => $stage ) :
						$stage_url = admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=placement&stage_pool=' . rawurlencode( $pool ) . '&stage=' . $stage_index );
						$stage_label = sprintf( __( 'Pool %1$s: %2$s', 'table-tennis-tournament-for-clubs' ), $pool, $stage['label'] );
						?>
						<a class="nav-tab <?php echo $selected_stage_pool === $pool && $selected_stage_index === $stage_index ? 'nav-tab-active' : ''; ?>" href="<?php echo esc_url( $stage_url ); ?>"><?php echo esc_html( $stage_label ); ?></a>
					<?php endforeach; ?>
				<?php endforeach; ?>
			</nav>
		<?php endif; ?>
		<?php if ( null !== $selected_stage ) : ?>
			<?php $stage_games = $game_formats[ $selected_stage['phase'] ]; ?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-scores-form">
				<input type="hidden" name="action" value="tttc_save_scores"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>"><input type="hidden" name="tttc_placement_phase" value="placement"><input type="hidden" name="tttc_placement_stage_pool" value="<?php echo esc_attr( $selected_stage_pool ); ?>"><input type="hidden" name="tttc_placement_stage_index" value="<?php echo esc_attr( $selected_stage_index ); ?>">
				<?php wp_nonce_field( 'tttc_save_scores', 'tttc_scores_nonce' ); ?>
				<section class="tttc-admin-placement-group">
					<h2><?php echo esc_html( sprintf( __( 'Pool %1$s: %2$s', 'table-tennis-tournament-for-clubs' ), $selected_stage_pool, $selected_stage['label'] ) ); ?></h2>
					<div class="tttc-scores-table-wrap"><table class="widefat striped tttc-scores-table">
						<thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $stage_games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?></tr></thead>
						<tbody>
						<?php foreach ( $selected_stage['matches'] as $match_index => $match ) :
							$available = $match['players'][0] && $match['players'][1];
							$score_key = $match['score_key'];
							$match_scores = isset( $all_scores[ $score_key ] ) ? $all_scores[ $score_key ] : array();
							?>
							<tr><td><?php echo esc_html( $match_index + 1 ); ?></td><td><?php echo esc_html( $available ? $match['players'][0]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td><td><?php echo esc_html( $available ? $match['players'][1]->post_title : __( 'Waiting for previous matches', 'table-tennis-tournament-for-clubs' ) ); ?></td>
								<?php for ( $game = 0; $game < $stage_games; $game++ ) : $game_score = isset( $match_scores[ $game ] ) ? $match_scores[ $game ] : array( '', '' ); ?>
									<td><span class="tttc-score-pair"><input class="small-text" type="number" min="0" inputmode="numeric" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][0]" value="<?php echo esc_attr( $game_score[0] ); ?>" <?php disabled( ! $available ); ?>><input class="small-text" type="number" min="0" inputmode="numeric" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][1]" value="<?php echo esc_attr( $game_score[1] ); ?>" <?php disabled( ! $available ); ?>></span></td>
								<?php endfor; ?>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table></div>
				</section>
				<p><button class="button button-primary"><?php esc_html_e( 'Save scores', 'table-tennis-tournament-for-clubs' ); ?></button></p>
			</form>
		<?php endif; ?>
		<?php if ( null === $selected_round && ! $print_all_groups ) : ?>
			<?php if ( null === $selected_stage ) : ?><p><?php esc_html_e( 'This pool has no matches; its standings are ready.', 'table-tennis-tournament-for-clubs' ); ?></p><?php endif; ?>
		<?php endif; ?>
		<?php if ( ( null !== $selected_round || $print_all_groups ) && null === $selected_stage ) : ?>
		<?php if ( $print_all_groups ) : ?>
		<div class="tttc-scores-form tttc-print-all-groups">
		<?php else : ?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-scores-form">
			<input type="hidden" name="action" value="tttc_save_scores"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>"><input type="hidden" name="tttc_placement_phase" value="<?php echo esc_attr( $phase ); ?>"><input type="hidden" name="tttc_placement_group" value="<?php echo esc_attr( $selected_group ); ?>"><input type="hidden" name="tttc_placement_round" value="">
			<?php wp_nonce_field( 'tttc_save_scores', 'tttc_scores_nonce' ); ?>
		<?php endif; ?>
			<div class="tttc-scores-toolbar"><button type="button" class="button tttc-print-button" onclick="window.print(); return false;"><?php esc_html_e( 'Print scores', 'table-tennis-tournament-for-clubs' ); ?></button></div>
			<?php foreach ( $visible_schedule as $group_key => $group_schedule ) : ?>
				<section class="tttc-admin-placement-group">
					<h2><?php echo esc_html( $group_schedule['label'] ); ?> <span class="description">(<?php echo esc_html( count( $group_schedule['players'] ) ); ?> <?php esc_html_e( 'players', 'table-tennis-tournament-for-clubs' ); ?>)</span></h2>
					<?php foreach ( $group_schedule['rounds'] as $round_number => $round ) : ?>
						<h3><?php echo esc_html( sprintf( __( 'Round %d', 'table-tennis-tournament-for-clubs' ), $round_number + 1 ) ); ?></h3>
						<div class="tttc-scores-table-wrap"><table class="widefat striped tttc-scores-table">
							<thead><tr><th><?php esc_html_e( 'Match', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 1', 'table-tennis-tournament-for-clubs' ); ?></th><th><?php esc_html_e( 'Player 2', 'table-tennis-tournament-for-clubs' ); ?></th><?php for ( $game = 1; $game <= $games; $game++ ) : ?><th><?php echo esc_html( sprintf( __( 'Game %d', 'table-tennis-tournament-for-clubs' ), $game ) ); ?></th><?php endfor; ?></tr></thead>
							<tbody>
								<?php foreach ( $round as $match_index => $match ) :
									$score_key = TTTC_Plugin::placement_score_key( $phase, 'placement' === $phase ? $group_schedule['score_group'] : '', $match[0]->ID, $match[1]->ID );
									$match_scores = isset( $all_scores[ $score_key ] ) ? $all_scores[ $score_key ] : array();
									?>
									<tr><td><?php echo esc_html( $match_index + 1 ); ?></td><td><?php echo esc_html( $match[0]->post_title ); ?></td><td><?php echo esc_html( $match[1]->post_title ); ?></td>
										<?php for ( $game = 0; $game < $games; $game++ ) : $game_score = isset( $match_scores[ $game ] ) ? $match_scores[ $game ] : array( '', '' ); ?>
											<td><span class="tttc-score-pair"><input class="small-text" type="number" min="0" inputmode="numeric" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][0]" value="<?php echo esc_attr( $game_score[0] ); ?>"><input class="small-text" type="number" min="0" inputmode="numeric" name="scores[<?php echo esc_attr( $score_key ); ?>][<?php echo esc_attr( $game ); ?>][1]" value="<?php echo esc_attr( $game_score[1] ); ?>"></span></td>
										<?php endfor; ?>
									</tr>
								<?php endforeach; ?>
							</tbody>
						</table></div>
					<?php endforeach; ?>
				</section>
			<?php endforeach; ?>
			<?php if ( ! $print_all_groups ) : ?><p><button class="button button-primary"><?php esc_html_e( 'Save scores', 'table-tennis-tournament-for-clubs' ); ?></button></p><?php endif; ?>
		<?php if ( $print_all_groups ) : ?>
		</div>
		<?php else : ?>
		</form>
		<?php endif; ?>
		<?php endif; ?>
	<?php endif; ?>
</div>
		<?php
	}

	public function placement_pools_page() {
		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_id = isset( $_GET['tournament_id'] ) ? absint( $_GET['tournament_id'] ) : 0;
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) || 'placement_pools' !== TTTC_Plugin::get_tournament_competition_format( $tournament_id ) || count( $this->assigned_player_ids( $tournament_id ) ) < 9 ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		$schedule = TTTC_Public::instance()->tournament_schedule( $tournament_id );
		$scores = $this->saved_scores( $tournament_id );
		$competition = TTTC_Competition::calculate( $schedule, $scores, TTTC_Plugin::get_tournament_game_formats( $tournament_id )['preliminary'], true );
		$complete = ! empty( $schedule );
		foreach ( $competition['groups'] as $group ) {
			if ( empty( $group['complete'] ) ) {
				$complete = false;
				break;
			}
		}
		global $wpdb;
		$has_placement_scores = $this->has_placement_match_scores( $tournament_id );
		$pool_assignments = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, true );
		$notice = '';
		if ( isset( $_GET['error'] ) ) {
			$notice = __( 'The pool assignments must include every tournament player exactly once and at least one player in each pool.', 'table-tennis-tournament-for-clubs' );
		}
		if ( isset( $_GET['updated'] ) ) {
			$notice = __( 'Placement pool assignments updated.', 'table-tennis-tournament-for-clubs' );
		}
		if ( ! is_array( $pool_assignments ) && $complete ) {
			$pool_assignments = array( 'A' => array(), 'B' => array(), 'C' => array() );
			foreach ( $competition['groups'] as $group ) {
				foreach ( $group['standings'] as $rank => $standing ) {
					$pool = ( 0 === $rank ) ? 'A' : ( 1 === $rank || 3 === $rank ? 'B' : ( 2 === $rank ? 'C' : '' ) );
					if ( '' !== $pool ) {
						$pool_assignments[ $pool ][] = (int) $standing['player']->ID;
					}
				}
			}
		}
		$pool_players = array( 'A' => array(), 'B' => array(), 'C' => array() );
		if ( $complete ) {
			foreach ( $competition['groups'] as $group_index => $group ) {
				foreach ( $group['standings'] as $rank => $standing ) {
					$player_id = (int) $standing['player']->ID;
					$current_pool = '';
					foreach ( array( 'A', 'B', 'C' ) as $pool ) {
						if ( isset( $pool_assignments[ $pool ] ) && in_array( $player_id, $pool_assignments[ $pool ], true ) ) {
							$current_pool = $pool;
							break;
						}
					}
					if ( '' === $current_pool ) {
						$current_pool = ( 0 === $rank ) ? 'A' : ( 1 === $rank || 3 === $rank ? 'B' : 'C' );
					}
					$pool_players[ $current_pool ][] = array(
						'player'      => $standing['player'],
						'group_index' => $group_index,
						'rank'        => $rank,
					);
				}
			}
		}
		?>
<div class="wrap">
	<h1><?php echo esc_html( get_the_title( $tournament_id ) . ' - ' . __( 'Placement pools A/B/C', 'table-tennis-tournament-for-clubs' ) ); ?></h1>
	<?php if ( $notice ) : ?><div class="notice <?php echo isset( $_GET['error'] ) ? 'notice-error' : 'notice-success'; ?> is-dismissible"><p><?php echo esc_html( $notice ); ?></p></div><?php endif; ?>
	<?php if ( ! $complete ) : ?>
		<p><?php esc_html_e( 'Complete every preliminary group match before assigning players to pools.', 'table-tennis-tournament-for-clubs' ); ?></p>
		<p><a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id ) ); ?>"><?php esc_html_e( 'Back to preliminary scores', 'table-tennis-tournament-for-clubs' ); ?></a></p>
	<?php elseif ( $has_placement_scores ) : ?>
		<p><?php esc_html_e( 'Placement matches have started, so pool assignments are locked.', 'table-tennis-tournament-for-clubs' ); ?></p>
		<div class="tttc-placement-pools">
			<?php foreach ( array( 'A', 'B', 'C' ) as $pool ) : ?>
				<section class="tttc-placement-pool" aria-labelledby="<?php echo esc_attr( 'tttc-placement-pool-' . $pool ); ?>">
					<h2 id="<?php echo esc_attr( 'tttc-placement-pool-' . $pool ); ?>">
						<?php echo esc_html( sprintf( __( 'Pool %s', 'table-tennis-tournament-for-clubs' ), $pool ) ); ?>
						<span class="tttc-placement-pool-count"><?php echo esc_html( count( $pool_players[ $pool ] ) ); ?></span>
					</h2>
					<ul class="tttc-placement-pool-assignments">
						<?php foreach ( $pool_players[ $pool ] as $assignment ) : ?>
							<li>
								<strong><?php echo esc_html( $assignment['player']->post_title ); ?></strong>
								<span><?php echo esc_html( sprintf( __( 'Group %1$d, place %2$d', 'table-tennis-tournament-for-clubs' ), $assignment['group_index'] + 1, $assignment['rank'] + 1 ) ); ?></span>
							</li>
						<?php endforeach; ?>
					</ul>
				</section>
			<?php endforeach; ?>
		</div>
	<?php else : ?>
		<p><?php esc_html_e( 'Suggested pools are based on preliminary group places. Drag players between pools, or use each player’s pool selector, before starting placement matches.', 'table-tennis-tournament-for-clubs' ); ?></p>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-placement-pools-form">
			<input type="hidden" name="action" value="tttc_save_placement_pools"><input type="hidden" name="tournament_id" value="<?php echo esc_attr( $tournament_id ); ?>">
			<?php wp_nonce_field( 'tttc_save_placement_pools_' . $tournament_id, 'tttc_placement_pools_nonce' ); ?>
			<div class="tttc-placement-pools">
				<?php foreach ( array( 'A', 'B', 'C' ) as $pool ) : ?>
					<section class="tttc-placement-pool" aria-labelledby="<?php echo esc_attr( 'tttc-placement-pool-' . $pool ); ?>">
						<h2 id="<?php echo esc_attr( 'tttc-placement-pool-' . $pool ); ?>">
							<?php echo esc_html( sprintf( __( 'Pool %s', 'table-tennis-tournament-for-clubs' ), $pool ) ); ?>
							<span class="tttc-placement-pool-count" data-pool-count="<?php echo esc_attr( $pool ); ?>"><?php echo esc_html( count( $pool_players[ $pool ] ) ); ?></span>
						</h2>
						<ul class="tttc-placement-pool-list" data-pool="<?php echo esc_attr( $pool ); ?>">
							<?php foreach ( $pool_players[ $pool ] as $assignment ) :
								$player_id = (int) $assignment['player']->ID;
								?>
								<li class="tttc-placement-player">
									<strong><?php echo esc_html( $assignment['player']->post_title ); ?></strong>
									<span class="tttc-placement-player-place"><?php echo esc_html( sprintf( __( 'Group %1$d, place %2$d', 'table-tennis-tournament-for-clubs' ), $assignment['group_index'] + 1, $assignment['rank'] + 1 ) ); ?></span>
									<label>
										<span class="screen-reader-text"><?php esc_html_e( 'Placement pool', 'table-tennis-tournament-for-clubs' ); ?></span>
										<select class="tttc-placement-player-pool" name="pool_assignments[<?php echo esc_attr( $player_id ); ?>]">
											<?php foreach ( array( 'A', 'B', 'C' ) as $option_pool ) : ?>
												<option value="<?php echo esc_attr( $option_pool ); ?>" <?php selected( $pool, $option_pool ); ?>><?php echo esc_html( sprintf( __( 'Pool %s', 'table-tennis-tournament-for-clubs' ), $option_pool ) ); ?></option>
											<?php endforeach; ?>
										</select>
									</label>
								</li>
							<?php endforeach; ?>
						</ul>
					</section>
				<?php endforeach; ?>
			</div>
			<p><button class="button button-primary"><?php esc_html_e( 'Save pool assignments and continue', 'table-tennis-tournament-for-clubs' ); ?></button></p>
		</form>
	<?php endif; ?>
</div>
		<?php
	}

	public function save_placement_pools() {
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_placement_pools_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_placement_pools_nonce'] ) ), 'tttc_save_placement_pools_' . $tournament_id ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) || 'placement_pools' !== TTTC_Plugin::get_tournament_competition_format( $tournament_id ) || count( $this->assigned_player_ids( $tournament_id ) ) < 9 ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		global $wpdb;
		$has_placement_scores = $this->has_placement_match_scores( $tournament_id );
		$schedule = TTTC_Public::instance()->tournament_schedule( $tournament_id );
		$competition = TTTC_Competition::calculate( $schedule, $this->saved_scores( $tournament_id ), TTTC_Plugin::get_tournament_game_formats( $tournament_id )['preliminary'], true );
		$complete = ! empty( $schedule );
		foreach ( $competition['groups'] as $group ) {
			if ( empty( $group['complete'] ) ) {
				$complete = false;
				break;
			}
		}
		if ( $has_placement_scores || ! $complete ) {
			wp_die( esc_html__( 'Pool assignments can only be changed after all preliminary matches and before placement scores are entered.', 'table-tennis-tournament-for-clubs' ) );
		}
		$player_ids = array_keys( array_reduce( $schedule, function( $ids, $group ) {
			foreach ( $group['players'] as $player ) {
				$ids[ (int) $player->ID ] = true;
			}
			return $ids;
		}, array() ) );
		$submitted = isset( $_POST['pool_assignments'] ) && is_array( $_POST['pool_assignments'] ) ? wp_unslash( $_POST['pool_assignments'] ) : array();
		$pools = array( 'A' => array(), 'B' => array(), 'C' => array() );
		foreach ( $player_ids as $player_id ) {
			$pool = isset( $submitted[ $player_id ] ) && is_scalar( $submitted[ $player_id ] ) ? strtoupper( sanitize_key( (string) $submitted[ $player_id ] ) ) : '';
			if ( ! isset( $pools[ $pool ] ) ) {
				wp_safe_redirect( admin_url( 'admin.php?page=tttc-placement-pools&tournament_id=' . $tournament_id . '&error=1' ) );
				exit;
			}
			$pools[ $pool ][] = (int) $player_id;
		}
		if ( count( $submitted ) !== count( $player_ids ) || empty( $pools['A'] ) || empty( $pools['B'] ) || empty( $pools['C'] ) ) {
			wp_safe_redirect( admin_url( 'admin.php?page=tttc-placement-pools&tournament_id=' . $tournament_id . '&error=1' ) );
			exit;
		}
		$wpdb->query( $wpdb->prepare( 'DELETE FROM %i WHERE tournament_id = %d AND match_key LIKE %s', TTTC_Plugin::scores_table_name(), $tournament_id, 'p%' ) );
		update_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS, $pools );
		wp_safe_redirect( admin_url( 'admin.php?page=tttc-scores&tournament_id=' . $tournament_id . '&phase=placement' ) );
		exit;
	}

	private function has_placement_match_scores( $tournament_id ) {
		global $wpdb;
		$rows = $wpdb->get_col( $wpdb->prepare( 'SELECT scores FROM %i WHERE tournament_id = %d AND match_key LIKE %s', TTTC_Plugin::scores_table_name(), $tournament_id, 'p%' ) );
		foreach ( $rows as $encoded_scores ) {
			$scores = json_decode( $encoded_scores, true );
			if ( ! is_array( $scores ) ) {
				continue;
			}
			foreach ( $scores as $game_score ) {
				if ( is_array( $game_score ) && ( '' !== (string) ( isset( $game_score[0] ) ? $game_score[0] : '' ) || '' !== (string) ( isset( $game_score[1] ) ? $game_score[1] : '' ) ) ) {
					return true;
				}
			}
		}

		return false;
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
			// translators: 1: maximum number of games, 2: games required to win.
			return new WP_Error( 'incomplete_match', sprintf( __( 'A Best of %1$d match must have a winner with at least %2$d games won.', 'table-tennis-tournament-for-clubs' ), $games, $required ) );
		}

		return $validated;
	}

	private function score_error_transient_key( $tournament_id ) {
		return 'tttc_score_error_' . get_current_user_id() . '_' . absint( $tournament_id );
	}

	private function form_scores( $submitted, $schedule, $game_formats ) {
		$scores = array();
		$games = $game_formats['preliminary'];

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

		$no_follow_up_rounds = '1' === get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_NO_FOLLOW_UP, true );
		$competition = TTTC_Competition::calculate( $schedule, $scores, $game_formats, $no_follow_up_rounds );
		foreach ( $competition['stages'] as $stage ) {
			$games = $game_formats[ $stage['phase'] ];
			foreach ( $stage['matches'] as $match ) {
				if ( ! $match['players'][0] || ! $match['players'][1] ) {
					continue;
				}
				$match_key    = $match['score_key'];
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

		return $scores;
	}

	private function saved_scores( $tournament_id ) {
		global $wpdb;
		$rows   = $wpdb->get_results( $wpdb->prepare( 'SELECT match_key, scores FROM %i WHERE tournament_id = %d', TTTC_Plugin::scores_table_name(), $tournament_id ) );
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
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		$tournament_type = get_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_TYPE, true );
		$tournament_type = in_array( $tournament_type, array( 'senior', 'youth', 'both' ), true ) ? $tournament_type : 'both';
		$players         = get_posts( array( 'post_type' => TTTC_Plugin::PLAYER_POST_TYPE, 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_query' => array( array( 'key' => TTTC_Plugin::PLAYER_META_ACTIVE, 'value' => '1' ) ) ) );
		$assigned      = $this->assigned_player_ids( $tournament_id );
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Tournament Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
	<?php if ( isset( $_GET['updated'] ) ) : ?><div class="notice notice-success is-dismissible">
		<p><?php esc_html_e( 'Tournament players updated.', 'table-tennis-tournament-for-clubs' ); ?></p>
	</div><?php endif; ?>
	<?php if ( $tournament_id ) : ?>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-assignment-form">
		<input type="hidden" name="action" value="tttc_update_players"><input type="hidden" name="tournament_id"
			value="<?php echo esc_attr( $tournament_id ); ?>">
		<?php wp_nonce_field( 'tttc_update_players_' . $tournament_id, 'tttc_assignment_nonce' ); ?>
		<h2><?php echo esc_html( get_the_title( $tournament_id ) ); ?></h2>
		<p>
			<?php esc_html_e( 'Select the active players who will participate in this tournament.', 'table-tennis-tournament-for-clubs' ); ?>
		</p>
		<div class="nav-tab-wrapper tttc-player-type-tabs" role="group"
			aria-label="<?php esc_attr_e( 'Player type', 'table-tennis-tournament-for-clubs' ); ?>">
			<button type="button"
				class="nav-tab tttc-player-type-tab <?php echo 'senior' === $tournament_type ? 'nav-tab-active' : ''; ?>"
				data-player-type="senior"
				aria-pressed="<?php echo 'senior' === $tournament_type ? 'true' : 'false'; ?>"><?php esc_html_e( 'Senior', 'table-tennis-tournament-for-clubs' ); ?></button>
			<button type="button"
				class="nav-tab tttc-player-type-tab <?php echo 'youth' === $tournament_type ? 'nav-tab-active' : ''; ?>"
				data-player-type="youth"
				aria-pressed="<?php echo 'youth' === $tournament_type ? 'true' : 'false'; ?>"><?php esc_html_e( 'Youth', 'table-tennis-tournament-for-clubs' ); ?></button>
		</div>
		<table class="widefat striped" data-tournament-type="<?php echo esc_attr( $tournament_type ); ?>">
			<thead>
				<tr>
					<th class="check-column"><input type="checkbox" class="tttc-select-all"></th>
					<th><?php esc_html_e( 'Player', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php
					foreach ( $players as $player ) :
						$player_type = get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_TYPE, true );
						$player_type = $player_type ? $player_type : 'senior';
						$is_assigned = in_array( $player->ID, $assigned, true );
						$type_mismatch = 'both' !== $tournament_type && $player_type !== $tournament_type;
						?>
				<tr data-type="<?php echo esc_attr( $player_type ); ?>"
					class="<?php echo $type_mismatch ? 'tttc-row-hidden-by-type' : ''; ?>">
					<th class="check-column"><input type="checkbox" name="player_ids[]"
							value="<?php echo esc_attr( $player->ID ); ?>" <?php checked( $is_assigned ); ?>></th>
					<td><?php echo esc_html( $player->post_title ); ?></td>
					<td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button
				class="button button-primary"><?php esc_html_e( 'Save tournament players', 'table-tennis-tournament-for-clubs' ); ?></button>
		</p>
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
		if ( ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'You do not have permission to view this page.', 'table-tennis-tournament-for-clubs' ) );
		}
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Order Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
	<h2><?php echo esc_html( get_the_title( $tournament_id ) ); ?></h2>
	<p><a
			href="<?php echo esc_url( admin_url( 'admin.php?page=tttc-assignments&tournament_id=' . $tournament_id ) ); ?>"><?php esc_html_e( 'Manage', 'table-tennis-tournament-for-clubs' ); ?></a>
	</p>
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
<p class="description">
	<?php esc_html_e( 'Seeding is locked because scores have already been entered for this tournament.', 'table-tennis-tournament-for-clubs' ); ?>
</p>
<ol class="tttc-seed-list tttc-seed-list--locked">
	<?php foreach ( $seed_players as $player ) : ?>
	<li><?php echo esc_html( $player->post_title ); ?> <span
			class="tttc-seed-rating"><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></span>
	</li>
	<?php endforeach; ?>
</ol>
<?php else : ?>
<p>
	<?php esc_html_e( 'Drag players to set the strength order used for grouping. This overrides the rating-based order.', 'table-tennis-tournament-for-clubs' ); ?>
</p>
<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="tttc-seed-form">
	<input type="hidden" name="action" value="tttc_reorder_players"><input type="hidden" name="tournament_id"
		value="<?php echo esc_attr( $tournament_id ); ?>"><input type="hidden" class="tttc-seed-order-input"
		name="ordered_player_ids" value="">
	<?php wp_nonce_field( 'tttc_reorder_players_' . $tournament_id, 'tttc_reorder_nonce' ); ?>
	<ol class="tttc-seed-list" data-tournament-id="<?php echo esc_attr( $tournament_id ); ?>">
		<?php foreach ( $seed_players as $player ) : ?>
		<li data-player-id="<?php echo esc_attr( $player->ID ); ?>"><span class="tttc-seed-handle"
				aria-hidden="true">&#9776;</span> <?php echo esc_html( $player->post_title ); ?> <span
				class="tttc-seed-rating"><?php echo esc_html( get_post_meta( $player->ID, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></span>
		</li>
		<?php endforeach; ?>
	</ol>
	<p><button
			class="button button-primary"><?php esc_html_e( 'Save order', 'table-tennis-tournament-for-clubs' ); ?></button>
	</p>
</form>
<?php endif;
	}

	public function update_players() {
		$tournament_id = isset( $_POST['tournament_id'] ) ? absint( $_POST['tournament_id'] ) : 0;
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_assignment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_assignment_nonce'] ) ), 'tttc_update_players_' . $tournament_id ) ) {
			wp_die( esc_html__( 'The security check failed.', 'table-tennis-tournament-for-clubs' ) );
		}
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) ) {
			wp_die( esc_html__( 'The tournament could not be found.', 'table-tennis-tournament-for-clubs' ) );
		}
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
		foreach ( $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, seed FROM %i WHERE tournament_id = %d', $table, $tournament_id ) ) as $row ) {
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
		delete_post_meta( $tournament_id, TTTC_Plugin::TOURNAMENT_META_PLACEMENT_POOLS );
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
		if ( TTTC_Plugin::TOURNAMENT_POST_TYPE !== get_post_type( $tournament_id ) || ! current_user_can( 'edit_post', $tournament_id ) || TTTC_Plugin::has_scores( $tournament_id ) ) {
			wp_die( esc_html__( 'The seeding order can not be changed for this tournament.', 'table-tennis-tournament-for-clubs' ) );
		}

		global $wpdb;
		$table       = TTTC_Plugin::table_name();
		$current_ids = array();
		foreach ( $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM %i WHERE tournament_id = %d', $table, $tournament_id ) ) as $player_id ) {
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
		return array_map( 'intval', $wpdb->get_col( $wpdb->prepare( 'SELECT player_id FROM %i WHERE tournament_id = %d', TTTC_Plugin::table_name(), $tournament_id ) ) );
	}

	private function seed_ordered_players( $tournament_id ) {
		global $wpdb;
		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT player_id, seed FROM %i WHERE tournament_id = %d', TTTC_Plugin::table_name(), $tournament_id ) );
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
			if ( ! current_user_can( 'edit_post', $player_id ) ) {
				wp_die( esc_html__( 'You do not have permission to merge these players.', 'table-tennis-tournament-for-clubs' ) );
			}
		}
		?>
<div class="wrap">
	<h1><?php esc_html_e( 'Merge Players', 'table-tennis-tournament-for-clubs' ); ?></h1>
	<?php if ( isset( $_GET['tttc_merge_error'] ) ) : ?>
	<div class="notice notice-error">
		<p><?php esc_html_e( 'Select exactly two players to merge.', 'table-tennis-tournament-for-clubs' ); ?></p>
	</div>
	<?php endif; ?>
	<p>
		<?php esc_html_e( 'Choose which player record to keep. The other player will be permanently deleted and its tournament assignments and scores will be transferred to the player you keep.', 'table-tennis-tournament-for-clubs' ); ?>
	</p>
	<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="action" value="tttc_merge_players">
		<?php foreach ( $player_ids as $player_id ) : ?>
		<input type="hidden" name="player_ids[]" value="<?php echo esc_attr( $player_id ); ?>">
		<?php endforeach; ?>
		<?php wp_nonce_field( 'tttc_merge_players', 'tttc_merge_nonce' ); ?>
		<table class="widefat striped">
			<thead>
				<tr>
					<th class="check-column"><?php esc_html_e( 'Keep', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Name', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Email', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Gender', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Type', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Active', 'table-tennis-tournament-for-clubs' ); ?></th>
					<th><?php esc_html_e( 'Tournaments', 'table-tennis-tournament-for-clubs' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php foreach ( $player_ids as $index => $player_id ) : ?>
				<tr>
					<th class="check-column"><input type="radio" name="keep_player_id"
							value="<?php echo esc_attr( $player_id ); ?>" <?php checked( 0 === $index ); ?> required></th>
					<td><?php echo esc_html( get_the_title( $player_id ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_RATING, true ) ); ?></td>
					<td><?php echo esc_html( get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_EMAIL, true ) ); ?></td>
					<td>
						<?php echo esc_html( 'female' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_GENDER, true ) ? __( 'Female', 'table-tennis-tournament-for-clubs' ) : __( 'Male', 'table-tennis-tournament-for-clubs' ) ); ?>
					</td>
					<td>
						<?php echo esc_html( 'youth' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_TYPE, true ) ? __( 'Youth', 'table-tennis-tournament-for-clubs' ) : __( 'Senior', 'table-tennis-tournament-for-clubs' ) ); ?>
					</td>
					<td>
						<?php echo '1' === get_post_meta( $player_id, TTTC_Plugin::PLAYER_META_ACTIVE, true ) ? esc_html__( 'Yes', 'table-tennis-tournament-for-clubs' ) : esc_html__( 'No', 'table-tennis-tournament-for-clubs' ); ?>
					</td>
					<td><?php echo esc_html( count( $this->assigned_player_ids_for_player( $player_id ) ) ); ?></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button
				class="button button-primary"><?php esc_html_e( 'Merge players', 'table-tennis-tournament-for-clubs' ); ?></button>
		</p>
	</form>
</div>
<?php
	}

	public function merge_players() {
		if ( ! current_user_can( 'edit_posts' ) || ! isset( $_POST['tttc_merge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tttc_merge_nonce'] ) ), 'tttc_merge_players' ) ) {
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

		$tournament_ids = $wpdb->get_col( $wpdb->prepare( 'SELECT tournament_id FROM %i WHERE player_id = %d', $assign_table, $source_id ) );
		foreach ( $tournament_ids as $tournament_id ) {
			$already_assigned = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE tournament_id = %d AND player_id = %d', $assign_table, $tournament_id, $keep_id ) );
			if ( $already_assigned ) {
				$wpdb->delete( $assign_table, array( 'tournament_id' => $tournament_id, 'player_id' => $source_id ), array( '%d', '%d' ) );
			} else {
				$wpdb->update( $assign_table, array( 'player_id' => $keep_id ), array( 'tournament_id' => $tournament_id, 'player_id' => $source_id ), array( '%d' ), array( '%d', '%d' ) );
			}
		}

		$rows = $wpdb->get_results( $wpdb->prepare( 'SELECT * FROM %i WHERE player_one_id = %d OR player_two_id = %d', $scores_table, $source_id, $source_id ) );
		foreach ( $rows as $row ) {
			$opponent_id = (int) $row->player_one_id === (int) $source_id ? (int) $row->player_two_id : (int) $row->player_one_id;

			// The merged pair played each other, so this match no longer makes sense.
			if ( $opponent_id === (int) $keep_id ) {
				$wpdb->delete( $scores_table, array( 'id' => $row->id ), array( '%d' ) );
				continue;
			}

			$new_match_key = $this->match_key( $keep_id, $opponent_id );
			$existing      = $wpdb->get_var( $wpdb->prepare( 'SELECT id FROM %i WHERE tournament_id = %d AND match_key = %s AND id != %d', $scores_table, $row->tournament_id, $new_match_key, $row->id ) );

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
		return $wpdb->get_col( $wpdb->prepare( 'SELECT tournament_id FROM %i WHERE player_id = %d', TTTC_Plugin::table_name(), $player_id ) );
	}

	public function enqueue_assets( $hook ) {
		$screen           = get_current_screen();
		$screen_post_type = $screen ? $screen->post_type : '';
		if ( ! $screen_post_type && isset( $_GET['post_type'] ) ) {
			$screen_post_type = sanitize_key( wp_unslash( $_GET['post_type'] ) );
		}

		if ( false !== strpos( $hook, 'tttc-' ) || in_array( $screen_post_type, array( TTTC_Plugin::PLAYER_POST_TYPE, TTTC_Plugin::TOURNAMENT_POST_TYPE ), true ) ) {
			wp_enqueue_style( 'tttc-admin', TTTC_URL . 'assets/admin.css', array(), TTTC_VERSION . '.' . filemtime( TTTC_PATH . 'assets/admin.css' ) );
			wp_enqueue_script( 'tttc-admin', TTTC_URL . 'assets/admin.js', array( 'jquery', 'jquery-ui-sortable' ), TTTC_VERSION . '.' . filemtime( TTTC_PATH . 'assets/admin.js' ), true );
			if ( TTTC_Plugin::TOURNAMENT_POST_TYPE === $screen_post_type && in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
				wp_enqueue_media();
			}

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

			if ( TTTC_Plugin::TOURNAMENT_POST_TYPE === $screen_post_type && 'edit.php' === $hook ) {
				wp_localize_script(
					'tttc-admin',
					'tttcStatusData',
					array(
						'ajaxUrl' => admin_url( 'admin-ajax.php' ),
						'saved'   => __( 'Saved', 'table-tennis-tournament-for-clubs' ),
						'error'   => __( 'Could not save status.', 'table-tennis-tournament-for-clubs' ),
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