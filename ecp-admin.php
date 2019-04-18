<?php
/**
 * Class defining admin functions
 *
 * @package    (p2pecp\)
 */

defined( 'ABSPATH' ) || die( 'Bitcoin is for all!' );

// Include everything.
require dirname( __FILE__ ) . '/ecp-include-all.php';

// ===========================================================================
// Global vars.
global $g_ecp_plugin_directory_url;
$g_ecp_plugin_directory_url = plugins_url( '', __FILE__ );

global $g_ecp_cron_script_url;
$g_ecp_cron_script_url = $g_ecp_plugin_directory_url . '/ecp-cron.php';

// ===========================================================================
// ===========================================================================
// Global default settings
global $g_ecp_config_defaults;
$g_ecp_config_defaults = array(

	// ------- Hidden constants
	'assigned_address_expires_in_mins'     => 4 * 60,   // 4 hours to pay for order and receive necessary number of confirmations.
	'funds_received_value_expires_in_mins' => '5',      // 'received_funds_checked_at' is fresh (considered to be a valid value) if it was last checked within 'funds_received_value_expires_in_mins' minutes.
	'max_blockchains_api_failures'         => '3',    // Return error after this number of sequential failed attempts to retrieve blockchain data.
	'max_unusable_generated_addresses'     => '20',   // Return error after this number of unusable (non-empty) bitcoin addresses were sequentially generated.
	'blockchain_api_timeout_secs'          => '20',   // Connection and request timeouts for get operations dealing with blockchain requests.
	'exchange_rate_api_timeout_secs'       => '10',   // Connection and request timeouts for get operations dealing with exchange rate API requests.
	'soft_cron_job_schedule_name'          => 'minutes_1',   // WP cron job frequency.
	'reuse_expired_addresses'              => '0',   // True - may reduce anonymouty of store customers (someone may click/generate bunch of fake orders to list many addresses that in a future will be used by real customers).
													// False - better anonymouty but may leave many addresses in wallet unused (and hence will require very high 'gap limit') due to many unpaid order clicks.
													// In this case it is recommended to regenerate new wallet after 'gap limit' reaches 1000.
	'delete_db_tables_on_uninstall'        => '0',
	'autocomplete_paid_orders'             => '1',
	'enable_soft_cron_job'                 => '1',    // Enable "soft" Wordpress-driven cron jobs.
);
// ===========================================================================
/**
 * Returns plugin wide settings
 *
 * @return array       array containing all the settings, some settings are arrays with more values
 */
function ecp__get_settings() {
	global   $g_ecp_plugin_directory_url;
	global   $g_ecp_config_defaults;

	$ecp_settings = get_option( ECP_SETTINGS_NAME );
	if ( ! is_array( $ecp_settings ) ) {
		$ecp_settings = $g_ecp_config_defaults;
	}

	return $ecp_settings;
}
// ===========================================================================
/**
 * Updates the plugin wide settings
 * This can be
 *
 * @return [type] [description]
 */
function ecp__update_settings() {
	if ( $ecp_use_these_settings ) {

		update_option( ECP_SETTINGS_NAME, $ecp_use_these_settings );
		return;
	}

	global   $g_ecp_config_defaults;

	// Load current settings and overwrite them with whatever values are present on submitted form
	$ecp_settings = ecp__get_settings();

	foreach ( $g_ecp_config_defaults as $k => $v ) {
		if ( ! isset( $ecp_settings[ $k ] ) ) {
			$ecp_settings[ $k ] = '';
		} // Force set to something.

		// if no old value is present and no new value is given
		// we want to set the settings to the default
		$value = $v;
		if ( isset( $_POST[ $k ] ) ) {
			// we have a new value that we want to set
			$value = $_POST[ $k ];
		} elseif ( $ecp_settings[ $k ] != '' ) {
			// there is a value and we do not want to change it.
			continue;
		}

		ECP__update_individual_ecp_setting( $ecp_settings[ $k ], $value );
	}

	update_option( ECP_SETTINGS_NAME, $ecp_settings );
}
// ===========================================================================
// ===========================================================================
// Takes care of recursive updating
function ECP__update_individual_ecp_setting( &$ecp_current_setting, $ecp_new_setting ) {
	if ( is_string( $ecp_new_setting ) ) {
		$ecp_current_setting = ECP__stripslashes( $ecp_new_setting );
	} elseif ( is_array( $ecp_new_setting ) ) {  // Note: new setting may not exist yet in current setting: curr[t5] - not set yet, while new[t5] set.
		// Need to do recursive
		foreach ( $ecp_new_setting as $k => $v ) {
			if ( ! isset( $ecp_current_setting[ $k ] ) ) {
				$ecp_current_setting[ $k ] = '';
			}   // If not set yet - force set it to something.
			ECP__update_individual_ecp_setting( $ecp_current_setting[ $k ], $v );
		}
	} else {
		$ecp_current_setting = $ecp_new_setting;
	}
}
// ===========================================================================
// ===========================================================================
//
// Reset settings only for one screen
function ECP__reset_partial_settings( $also_reset_persistent_settings = false ) {
	global   $g_ecp_config_defaults;

	// Load current settings and overwrite ones that are present on submitted form with defaults
	$ecp_settings = ecp__get_settings();

	foreach ( $_POST as $k => $v ) {
		if ( isset( $g_ecp_config_defaults[ $k ] ) ) {
			if ( ! isset( $ecp_settings[ $k ] ) ) {
				$ecp_settings[ $k ] = '';
			} // Force set to something.
			ECP__update_individual_ecp_setting( $ecp_settings[ $k ], $g_ecp_config_defaults[ $k ] );
		}
	}

	update_option( ECP_SETTINGS_NAME, $ecp_settings );
}
// ===========================================================================
// ===========================================================================
function ECP__reset_all_settings( $also_reset_persistent_settings = false ) {
	global   $g_ecp_config_defaults;

	update_option( ECP_SETTINGS_NAME, $g_ecp_config_defaults );
}
// ===========================================================================
// ===========================================================================
// Recursively strip slashes from all elements of multi-nested array
function ECP__stripslashes( &$val ) {
	if ( is_string( $val ) ) {
		return ( stripslashes( $val ) );
	}
	if ( ! is_array( $val ) ) {
		return $val;
	}

	foreach ( $val as $k => $v ) {
		$val[ $k ] = ECP__stripslashes( $v );
	}

	return $val;
}
// ===========================================================================
// ===========================================================================
function ECP__update_api_key() {
	$ecp_settings = ecp__get_settings();

	if ( ! is_array( $ecp_settings['api'] ) ) {
		$ecp_settings['api'] = array();
	}

	if ( ! is_array( $ecp_settings['api']['key'] ) ) {
		$ecp_settings['api']['key'] = array();
	}

	if ( isset( $_POST['api']['key'] ) ) {
		foreach ( $_POST['api']['key'] as $name => $key ) {
			$key = sanitize_key( $key );
			$ecp_settings['api']['key'][ sanitize_text_field( $name ) ] = $key;
		}
	}
	update_option( ECP_SETTINGS_NAME, $ecp_settings );
}
// ===========================================================================
//
function ECP__update_cache( $exchange_rate, $exchange_rate_type ) {
	// Save new currency exchange rate info in cache
	$ecp_settings  = ecp__get_settings();
	$currency_code = get_woocommerce_currency();

	if ( ! is_array( $ecp_settings['exchange_rates'] ) ) {
		$ecp_settings['exchange_rates'] = array();
	}

	if ( ! is_array( $ecp_settings['exchange_rates'][ $currency_code ] ) ) {
		$ecp_settings['exchange_rates'][ $currency_code ] = array();
	}

	if ( ! is_array( $ecp_settings['exchange_rates'][ $currency_code ][ $exchange_rate_type ] ) ) {
		$ecp_settings['exchange_rates'][ $currency_code ][ $exchange_rate_type ] = array();
	}

	$ecp_settings['exchange_rates'][ $currency_code ][ $exchange_rate_type ]['time-last-checked'] = time();
	$ecp_settings['exchange_rates'][ $currency_code ][ $exchange_rate_type ]['exchange_rate']     = $exchange_rate;
	update_option( ECP_SETTINGS_NAME, $ecp_settings );
}

// ===========================================================================
/*
	----------------------------------
	: Table 'btc_addresses' :
	----------------------------------
	  status                "unused"      - never been used address with last known zero balance
							"assigned"    - order was placed and this address was assigned for payment
							"revalidate"  - assigned/expired, unused or unknown address suddenly got non-zero balance in it. Revalidate it for possible late order payment against meta_data.
							"used"        - order was placed and this address and payment in full was received. Address will not be used again.
							"xused"       - address was used (touched with funds) by unknown entity outside of this application. No metadata is present for this address, will not be able to correlated it with any order.
							"unknown"     - new address was generated but cannot retrieve balance due to blockchain API failure.
*/
function ECP__create_database_tables( $ecp_settings ) {
	$create_tables = array( 'TableBCH', 'TableBSV' );

	foreach ( $create_tables as $table ) {
		$table::create_database_tables();
	}
}
// ===========================================================================
// ===========================================================================
// NOTE: Irreversibly deletes all plugin tables and data
function ECP__delete_database_tables() {
	$create_tables = array( 'TableBCH', 'TableBSV' );

	foreach ( $create_tables as $table ) {
		$table::delete_database_tables();
	}
}
// ===========================================================================

