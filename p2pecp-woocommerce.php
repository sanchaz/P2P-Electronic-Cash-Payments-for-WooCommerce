<?php
/*
Plugin Name: P2P Electronic Cash Payments for WooCommerce
Plugin URI: https://github.com/sanchaz/P2P-Electronic-Cash-Payments-for-WooCommerce
Description: P2P for WooCommerce plugin allows you to accept payments in bitcoin cash and bitcoin sv for physical and digital products at your WooCommerce-powered online store.
Version: 1.00
Author: sanchaz
Author URI: https://cryptartica.com
License: GNU General Public License 2.0 (GPL) http://www.gnu.org/licenses/gpl.html
*/

defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );


// Include everything
require dirname( __FILE__ ) . '/ecp-include-all.php';

// ---------------------------------------------------------------------------
// Add hooks and filters
// create custom plugin settings menu
add_action( 'admin_menu', 'ECP_create_menu' );

register_activation_hook( __FILE__, 'ECP_activate' );
register_deactivation_hook( __FILE__, 'ECP_deactivate' );
register_uninstall_hook( __FILE__, 'ECP_uninstall' );

add_filter( 'cron_schedules', 'ECP__add_custom_scheduled_intervals' );
add_action( 'ECP_cron_action', 'ECP_cron_job_worker' );     // Multiple functions can be attached to 'ECP_cron_action' action

ECP_set_lang_file();
// ---------------------------------------------------------------------------
// ===========================================================================
// activating the default values
function ECP_activate() {
	global  $g_ecp_config_defaults;

	$ecp_default_options = $g_ecp_config_defaults;

	// This will overwrite default options with already existing options but leave new options (in case of upgrading to new version) untouched.
	$ecp_settings = ecp__get_settings();

	foreach ( $ecp_settings as $key => $value ) {
		$ecp_default_options[ $key ] = $value;
	}

	update_option( ECP_SETTINGS_NAME, $ecp_default_options );

	// Re-get new settings.
	$ecp_settings = ecp__get_settings();

	// Create necessary database tables if not already exists...
	ECP__create_database_tables( $ecp_settings );

	// ----------------------------------
	// Setup cron jobs
	if ( ! wp_next_scheduled( 'ECP_cron_action' ) ) {
		$cron_job_schedule_name = strpos( $_SERVER['HTTP_HOST'], 'ttt.com' ) === false ? $ecp_settings['soft_cron_job_schedule_name'] : 'seconds_30';
		wp_schedule_event( time(), $cron_job_schedule_name, 'ECP_cron_action' );
	}
	// ----------------------------------
}
// ---------------------------------------------------------------------------
// Cron Subfunctions
function ECP__add_custom_scheduled_intervals( $schedules ) {
	$schedules['seconds_30']  = array(
		'interval' => 30,
		'display'  => __( 'Once every 30 seconds' ),
	);     // For testing only.
	$schedules['minutes_1']   = array(
		'interval' => 1 * 60,
		'display'  => __( 'Once every 1 minute' ),
	);
	$schedules['minutes_2.5'] = array(
		'interval' => 2.5 * 60,
		'display'  => __( 'Once every 2.5 minutes' ),
	);
	$schedules['minutes_5']   = array(
		'interval' => 5 * 60,
		'display'  => __( 'Once every 5 minutes' ),
	);

	return $schedules;
}
// ---------------------------------------------------------------------------
// ===========================================================================
// ===========================================================================
// deactivating
function ECP_deactivate() {
	 // Do deactivation cleanup. Do not delete previous settings in case user will reactivate plugin again...
	// ----------------------------------
	// Clear cron jobs
	wp_clear_scheduled_hook( 'ECP_cron_action' );
	// ----------------------------------
}
// ===========================================================================
// ===========================================================================
// uninstalling
function ECP_uninstall() {
	$ecp_settings = ecp__get_settings();

	if ( $ecp_settings['delete_db_tables_on_uninstall'] ) {
		// delete all settings.
		delete_option( ECP_SETTINGS_NAME );

		// delete all DB tables and data.
		ECP__delete_database_tables();
	}
}
// ===========================================================================
// ===========================================================================
function ECP_create_menu() {
	// create new top-level menu
	// http://www.fileformat.info/info/unicode/char/e3f/index.htm
	add_menu_page(
		__( 'P2P Electronic Cash Payments (ECP)', ECP_I18N_DOMAIN ),                    // Page title
		__( 'P2P ECP', ECP_I18N_DOMAIN ),                        // Menu Title - lower corner of admin menu
		'administrator',                                        // Capability
		'ecp-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
		'ECP__render_general_settings_page',                   // Function
		plugins_url( '/images/bitcoin_16x.png', __FILE__ )      // Icon URL
	);

	add_submenu_page(
		'ecp-settings',                                        // Parent
		__( 'P2P Electronic Cash Payments (ECP)', ECP_I18N_DOMAIN ),                   // Page title
		__( 'General Settings', ECP_I18N_DOMAIN ),               // Menu Title
		'administrator',                                        // Capability
		'ecp-settings',                                        // Handle - First submenu's handle must be equal to parent's handle to avoid duplicate menu entry.
		'ECP__render_general_settings_page'                    // Function
	);

	add_submenu_page(
		'ecp-settings',                                        // Parent
		__( 'P2P Electronic Cash Payments (ECP) API Settings', ECP_I18N_DOMAIN ),       // Page title
		__( 'API Settings', ECP_I18N_DOMAIN ),                // Menu title
		'administrator',                                        // Capability
		'ecp-settings-advanced',                        //
		'ECP__render_advanced_settings_page'            // Function
	);
}
// ===========================================================================
// ===========================================================================
// load language files
function ECP_set_lang_file() {
	// set the language file
	$currentLocale = get_locale();
	if ( ! empty( $currentLocale ) ) {
		$moFile = dirname( __FILE__ ) . '/lang/' . $currentLocale . '.mo';
		if ( @file_exists( $moFile ) && is_readable( $moFile ) ) {
			load_textdomain( ECP_I18N_DOMAIN, $moFile );
		}
	}
}
// ===========================================================================
