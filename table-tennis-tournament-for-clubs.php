<?php
/**
 * Plugin Name: Table Tennis Tournament for Clubs
 * Description: Manage club table tennis players, tournaments, and tournament player assignments.
 * Version: 1.0.2
 * Requires at least: 6.3
 * Requires PHP: 7.4
 * Author: Club Tools
 * Text Domain: table-tennis-tournament-for-clubs
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'TTTC_VERSION', '1.0.2' );
define( 'TTTC_FILE', __FILE__ );
define( 'TTTC_PATH', plugin_dir_path( __FILE__ ) );
define( 'TTTC_URL', plugin_dir_url( __FILE__ ) );

require_once TTTC_PATH . 'includes/class-tttc-plugin.php';
require_once TTTC_PATH . 'includes/class-tttc-admin.php';
require_once TTTC_PATH . 'includes/class-tttc-public.php';

register_activation_hook( __FILE__, array( 'TTTC_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TTTC_Plugin', 'deactivate' ) );

TTTC_Plugin::instance();
