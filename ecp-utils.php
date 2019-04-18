<?php
defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );


// ===========================================================================
/*
   Input:
   ------
	  $order_info =
		 array (
			'order_id'        => $order_id,
			'order_total'     => $order_total_in_btc,
			'order_datetime'  => date('Y-m-d H:i:s T'),
			'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
			);
*/
// Returns:
// --------
/*
	$ret_info_array = array (
	   'result'                      => 'success', // OR 'error'
	   'message'                     => '...',
	   'host_reply_raw'              => '......',
	   'generated_bitcoin_address'   => '18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj', // or false
	   );
*/
class ElectrumBCHUtil extends ElectrumUtil {

	public function convert_gen_addr_to_addr_array( $gen_addr ) {
		return array(
			'btc_address'  => $gen_addr['generated_bitcoin_address'],
			'bch_cashaddr' => $gen_addr['generated_bch_cashaddr'],
		);
	}

	public function db_insert_new_address( $addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index ) {
		global $wpdb;

		$new_btc_address  = $addresses['btc_address'];
		$new_bch_cashaddr = $addresses['bch_cashaddr'];

		$btc_addresses_table_name = $this->get_table_name();
		$origin_id                = $this->electrum_mpk;

		$query =
			"INSERT INTO `$btc_addresses_table_name`
            (`btc_address`, `bch_cashaddr`, `origin_id`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
            ('$new_btc_address', '$new_bch_cashaddr', '$origin_id', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
		$wpdb->query( $query );
	}

	public function fetch_addresses_from_row( $address_row ) {
		return array(
			'btc_address'  => $address_row['btc_address'],
			'bch_cashaddr' => $address_row['bch_cashaddr'],
		);
	}

	public function get_bitcoin_variant() {
		return 'bch';
	}

	public function get_table_name() {
		return TableBCH::get_table_name();
	}

	public function make_return_address( $result, $message, $host_reply_raw, $addresses = null ) {
		$ret_info_array = array(
			'result'         => $result,
			'message'        => $message,
			'host_reply_raw' => $host_reply_raw,
		);
		if ( $result != 'success' ) {
			$ret_info_array['generated_bitcoin_address'] = false;
			$ret_info_array['generated_bch_cashaddr']    = false;
		} else {
			$ret_info_array['generated_bitcoin_address'] = $addresses['btc_address'];
			$ret_info_array['generated_bch_cashaddr']    = $addresses['bch_cashaddr'];
		}

		return $ret_info_array;
	}

	public function run_query_quick_address_scan( $current_time ) {
		global $wpdb;

		$assigned_address_expires_in_secs     = $this->ecp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->ecp_settings['funds_received_value_expires_in_mins'] * 60;

		if ( $this->ecp_settings['reuse_expired_addresses'] ) {
			$reuse_expired_addresses_freshb_query_part =
				"OR (`status`='assigned'
                AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
                AND (('$current_time' - `received_funds_checked_at`) < '$funds_received_value_expires_in_secs')
                )";
		} else {
			$reuse_expired_addresses_freshb_query_part = '';
		}

		// -------------------------------------------------------
		// Quick scan for ready-to-use address
		// NULL == not found
		// Retrieve:
		// 'unused'   - with fresh zero balances
		// 'assigned' - expired, with fresh zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address will be clean to use.
		$origin_id                = $this->electrum_mpk;
		$btc_addresses_table_name = $this->get_table_name();
		$query                    =
			"SELECT `btc_address`, `bch_cashaddr` FROM `$btc_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (`status`='unused' $reuse_expired_addresses_freshb_query_part)
             ORDER BY `index_in_wallet` ASC
             LIMIT 1;"; // Try to use lower indexes first
		$clean_address            = $wpdb->get_var( $query, 0, 0 );
		$bch_cashaddr             = $wpdb->get_var( null, 1, 0 );

		return array(
			'btc_address'  => $clean_address,
			'bch_cashaddr' => $bch_cashaddr,
		);
	}
}

class ElectrumBSVUtil extends ElectrumUtil {


	public function convert_gen_addr_to_addr_array( $gen_addr ) {
		return array( 'btc_address' => $gen_addr['generated_bitcoin_address'] );
	}

	public function db_insert_new_address( $addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index ) {
		global $wpdb;

		$new_btc_address = $addresses['btc_address'];

		$btc_addresses_table_name = $this->get_table_name();
		$origin_id                = $this->electrum_mpk;

		$query =
			"INSERT INTO `$btc_addresses_table_name`
            (`btc_address`, `origin_id`, `index_in_wallet`, `total_received_funds`, `received_funds_checked_at`, `status`) VALUES
            ('$new_btc_address', '$origin_id', '$next_key_index', '$funds_received', '$received_funds_checked_at_time', '$status');";
		$wpdb->query( $query );
	}

	public function fetch_addresses_from_row( $address_row ) {
		return array( 'btc_address' => $address_row['btc_address'] );
	}

	public function get_bitcoin_variant() {
		return 'bsv';
	}

	public function get_table_name() {
		return TableBSV::get_table_name();
	}

	public function make_return_address( $result, $message, $host_reply_raw, $addresses = null ) {
		$ret_info_array = array(
			'result'         => $result,
			'message'        => $message,
			'host_reply_raw' => $host_reply_raw,
		);
		if ( $result != 'success' ) {
			$ret_info_array['generated_bitcoin_address'] = false;
		} else {
			$ret_info_array['generated_bitcoin_address'] = $addresses['btc_address'];
		}

		return $ret_info_array;
	}

	public function run_query_quick_address_scan( $current_time ) {
		global $wpdb;

		$assigned_address_expires_in_secs     = $this->ecp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->ecp_settings['funds_received_value_expires_in_mins'] * 60;

		if ( $this->ecp_settings['reuse_expired_addresses'] ) {
			$reuse_expired_addresses_freshb_query_part =
				"OR (`status`='assigned'
                AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
                AND (('$current_time' - `received_funds_checked_at`) < '$funds_received_value_expires_in_secs')
                )";
		} else {
			$reuse_expired_addresses_freshb_query_part = '';
		}

		// -------------------------------------------------------
		// Quick scan for ready-to-use address
		// NULL == not found
		// Retrieve:
		// 'unused'   - with fresh zero balances
		// 'assigned' - expired, with fresh zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address will be clean to use.
		$origin_id                = $this->electrum_mpk;
		$btc_addresses_table_name = $this->get_table_name();
		$query                    =
			"SELECT `btc_address` FROM `$btc_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (`status`='unused' $reuse_expired_addresses_freshb_query_part)
             ORDER BY `index_in_wallet` ASC
             LIMIT 1;"; // Try to use lower indexes first
		$clean_address            = $wpdb->get_var( $query, 0, 0 );

		return array( 'btc_address' => $clean_address );
	}
}

abstract class ElectrumUtil {

	protected $electrum_mpk;
	protected $ecp_settings;
	protected $starting_index_for_new_btc_addresses;

	public function __construct( $electrum_mpk, $starting_index_for_new_btc_addresses ) {
		$this->electrum_mpk                         = $electrum_mpk;
		$this->ecp_settings                         = ecp__get_settings();
		$this->starting_index_for_new_btc_addresses = $starting_index_for_new_btc_addresses;
	}

	abstract public function db_insert_new_address( $addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index);

	abstract public function fetch_addresses_from_row( $address_row);

	public function fetch_address_meta( $addresses ) {
		global $wpdb;

		$btc_addresses_table_name = $this->get_table_name();
		$clean_address            = $addresses['btc_address'];
		$address_meta             = $wpdb->get_var( "SELECT `address_meta` FROM `$btc_addresses_table_name` WHERE `btc_address`='$clean_address'" );

		return $address_meta;
	}

	abstract public function get_bitcoin_variant();

	public function get_next_key_index() {
		global $wpdb;

		$btc_addresses_table_name = $this->get_table_name();
		$origin_id                = $this->electrum_mpk;

		$next_key_index = $wpdb->get_var( "SELECT MAX(`index_in_wallet`) AS `max_index_in_wallet` FROM `$btc_addresses_table_name` WHERE `origin_id`='$origin_id';" );
		if ( $next_key_index === null ) {
			$next_key_index = $this->starting_index_for_new_btc_addresses;
		} // Start generation of addresses from index #2 (skip two leading wallet's addresses)
		else {
			$next_key_index = $next_key_index + 1;
		}  // Continue with next index

		return $next_key_index;
	}

	abstract public function get_table_name();

	public function mark_address_as_assigned( $addresses, $remote_addr, $address_meta_serialized ) {
		global $wpdb;

		$btc_addresses_table_name = $this->get_table_name();
		$current_time             = time();
		$clean_address            = $addresses['btc_address'];
		$query                    =
				"UPDATE `$btc_addresses_table_name`
                 SET
                    `total_received_funds` = '0',
                    `received_funds_checked_at`='$current_time',
                    `status`='assigned',
                    `assigned_at`='$current_time',
                    `last_assigned_to_ip`='$remote_addr',
                    `address_meta`='$address_meta_serialized'
                 WHERE `btc_address`='$clean_address';";
		$ret_code                 = $wpdb->query( $query );
	}

	public function make_address_request_array( $addresses ) {
		$address_request_array                = array();
		$address_request_array['btc_address'] = $addresses['btc_address']; // $addresses["bch_cashaddr"];

		return $address_request_array;
	}

	abstract public function make_return_address( $result, $message, $host_reply_raw, $addresses = null);

	abstract public function run_query_quick_address_scan( $current_time);

	public function run_query_unknown_address_scan( $current_time ) {
		global $wpdb;

		$assigned_address_expires_in_secs     = $this->ecp_settings['assigned_address_expires_in_mins'] * 60;
		$funds_received_value_expires_in_secs = $this->ecp_settings['funds_received_value_expires_in_mins'] * 60;
		// -------------------------------------------------------
		// Find all unused addresses belonging to this mpk with possibly (to be verified right after) zero balances
		// Array(rows) or NULL
		// Retrieve:
		// 'unused'    - with old zero balances
		// 'unknown'   - ALL
		// 'assigned'  - expired with old zero balances (if 'reuse_expired_addresses' is true)
		//
		// Hence - any returned address with freshened balance==0 will be clean to use.
		if ( $this->ecp_settings['reuse_expired_addresses'] ) {
			$reuse_expired_addresses_oldb_query_part =
				"OR (`status`='assigned'
                 AND (('$current_time' - `assigned_at`) > '$assigned_address_expires_in_secs')
                 AND (('$current_time' - `received_funds_checked_at`) > '$funds_received_value_expires_in_secs')
                 )";
		} else {
			$reuse_expired_addresses_oldb_query_part = '';
		}

		$origin_id                = $this->electrum_mpk;
		$btc_addresses_table_name = $this->get_table_name();

		$query                                      =
			"SELECT * FROM `$btc_addresses_table_name`
             WHERE `origin_id`='$origin_id'
             AND `total_received_funds`='0'
             AND (
                `status`='unused'
                OR `status`='unknown'
                $reuse_expired_addresses_oldb_query_part
                )
             ORDER BY `index_in_wallet` ASC;"; // Try to use lower indexes first
		$addresses_to_verify_for_zero_balances_rows = $wpdb->get_results( $query, ARRAY_A );

		if ( ! is_array( $addresses_to_verify_for_zero_balances_rows ) ) {
			$addresses_to_verify_for_zero_balances_rows = array();
		}

		return $addresses_to_verify_for_zero_balances_rows;
	}

	public function set_address_status( $address_row, $balance, $new_status ) {
		global $wpdb;

		$btc_addresses_table_name           = $this->get_table_name();
		$current_time                       = time();
		$address_to_verify_for_zero_balance = $address_row['btc_address'];
		$query                              =
			"UPDATE `$btc_addresses_table_name`
             SET
             `status`='$new_status',
             `total_received_funds` = '$balance',
             `received_funds_checked_at`='$current_time'
             WHERE `btc_address`='$address_to_verify_for_zero_balance';";
		$ret_code                           = $wpdb->query( $query );
	}

	public function get_bitcoin_address_for_payment__electrum( $order_info ) {
		$funds_received_value_expires_in_secs = $this->ecp_settings['funds_received_value_expires_in_mins'] * 60;
		$assigned_address_expires_in_secs     = $this->ecp_settings['assigned_address_expires_in_mins'] * 60;

		$current_time = time();

		$clean_address = $this->run_query_quick_address_scan( $current_time );

		// -------------------------------------------------------
		if ( ! array_filter( $clean_address ) ) {

			$addresses_to_verify_for_zero_balances_rows = $this->run_query_unknown_address_scan( $current_time );

			// -------------------------------------------------------
			// Try to re-verify balances of existing addresses (with old or non-existing balances) before reverting to slow operation of generating new address.
			//
			$blockchains_api_failures = 0;
			foreach ( $addresses_to_verify_for_zero_balances_rows as $address_to_verify_for_zero_balance_row ) {
				// http://blockexplorer.com/q/getreceivedbyaddress/18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj
				// http://blockchain.info/q/getreceivedbyaddress/18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj [?confirmations=6]
				//
				$address_to_verify_for_zero_balance = $address_to_verify_for_zero_balance_row['btc_address'];

				// $address_request_array = array();
				// $address_request_array['btc_address'] = $address_to_verify_for_zero_balance;
				// $address_request_array['required_confirmations'] = 0;
				// $address_request_array['api_timeout'] = $this->ecp_settings['blockchain_api_timeout_secs'];
				// possible clean address
				$maybe_clean_address = $this->fetch_addresses_from_row( $address_to_verify_for_zero_balance_row );

				$ret_info_array = $this->getreceivedbyaddress_info( $maybe_clean_address );

				if ( $ret_info_array['balance'] === false ) {
					$blockchains_api_failures ++;
					if ( $blockchains_api_failures >= $this->ecp_settings['max_blockchains_api_failures'] ) {
						// Allow no more than 3 contigious blockchains API failures. After which return error reply.
						return $this->make_return_address( 'error', $ret_info_array['message'], $ret_info_array['host_reply_raw'] );
					}
				} elseif ( $ret_info_array['balance'] == 0 ) {
					// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
					$clean_address = $maybe_clean_address;
					break;
				} else {
					// Balance at this address suddenly became non-zero!
					// It means either order was paid after expiration or "unknown" address suddenly showed up with non-zero balance or payment was sent to this address outside of this online store business.
					// Mark it as 'revalidate' so cron job would check if that's possible delayed payment.
					//
					$address_meta = ECP_unserialize_address_meta( @$address_to_verify_for_zero_balance_row['address_meta'] );
					if ( isset( $address_meta['orders'][0] ) ) {
						$new_status = 'revalidate';
					} // Past orders are present. There is a chance (for cron job) to match this payment to past (albeit expired) order.
					else {
						$new_status = 'used';
					}       // No orders were ever placed to this address. Likely payment was sent to this address outside of this online store business.

					$this->set_address_status( $address_to_verify_for_zero_balance_row, $ret_info_array['balance'], $new_status );
				}
			}
			// -------------------------------------------------------
		}

		// -------------------------------------------------------
		if ( ! array_filter( $clean_address ) ) {
			// Still could not find unused virgin address. Time to generate it from scratch.
			/*
			Returns:
			   $ret_info_array = array (
				  'result'                      => 'success', // 'error'
				  'message'                     => '', // Failed to find/generate bitcoin address',
				  'host_reply_raw'              => '', // Error. No host reply availabe.',
				  'generated_bitcoin_address'   => '1FVai2j2FsFvCbgsy22ZbSMfUd3HLUHvKx', // false,
				  );
			*/
			$ret_addr_array = $this->generate_new_bitcoin_address_for_electrum_wallet();
			if ( $ret_addr_array['result'] == 'success' ) {
				$clean_address = $this->convert_gen_addr_to_addr_array( $ret_addr_array );
			}
		}
		// -------------------------------------------------------
		// -------------------------------------------------------
		if ( array_filter( $clean_address ) ) {
			/*
				  $order_info =
				  array (
					 'order_id'     => $order_id,
					 'order_total'  => $order_total_in_btc,
					 'order_datetime'  => date('Y-m-d H:i:s T'),
					 'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
					 );
			*/
			/*
			$address_meta =
			   array (
				  'orders' =>
					 array (
						// All orders placed on this address in reverse chronological order
						array (
						   'order_id'     => $order_id,
						   'order_total'  => $order_total_in_btc,
						   'order_datetime'  => date('Y-m-d H:i:s T'),
						   'requested_by_ip' => @$_SERVER['REMOTE_ADDR'],
						),
						array (
						   ...
						),
					 ),
				  'other_meta_info' => array (...)
			   );
			*/

			// Prepare `address_meta` field for this clean address.
			$address_meta = ECP_unserialize_address_meta( $this->fetch_address_meta( $clean_address ) );

			if ( ! isset( $address_meta['orders'] ) || ! is_array( $address_meta['orders'] ) ) {
				$address_meta['orders'] = array();
			}

			array_unshift( $address_meta['orders'], $order_info );    // Prepend new order to array of orders
			if ( count( $address_meta['orders'] ) > 10 ) {
				array_pop( $address_meta['orders'] );
			}   // Do not keep history of more than 10 unfullfilled orders per address.
			$address_meta_serialized = ECP_serialize_address_meta( $address_meta );

			// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
			//
			$this->mark_address_as_assigned( $clean_address, $order_info['requested_by_ip'], $address_meta_serialized );
			return $this->make_return_address( 'success', '', '', $clean_address );
		}
		// -------------------------------------------------------
		return $this->make_return_address( 'error', 'Failed to find/generate bitcoin cash address. ' . $ret_addr_array['message'], $ret_addr_array['host_reply_raw'] );
	}

	// ===========================================================================
	/*
	Returns:
	   $ret_info_array = array (
		  'result'                      => 'success', // 'error'
		  'message'                     => '', // Failed to find/generate bitcoin cash address',
		  'host_reply_raw'              => '', // Error. No host reply availabe.',
		  'generated_bitcoin_address'   => '18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj', // false,
		  );
	*/
	// If $this->ecp_settings or $electrum_mpk are missing - the best attempt will be made to manifest them.
	// For performance reasons it is better to pass in these vars. if available.
	//
	public function generate_new_bitcoin_address_for_electrum_wallet() {
		if ( ! $this->electrum_mpk ) {
			// Bitcoin gateway settings either were not saved
			return $this->make_return_address( 'error', 'No MPK passed and either no MPK present in copy-settings', '' );
		}

		$funds_received_value_expires_in_secs = $this->ecp_settings['funds_received_value_expires_in_mins'] * 60;
		$assigned_address_expires_in_secs     = $this->ecp_settings['assigned_address_expires_in_mins'] * 60;

		// Find next index to generate
		$next_key_index = $this->get_next_key_index();

		$addresses                = false;
		$total_new_keys_generated = 0;
		$blockchains_api_failures = 0;
		do {
			$addresses      = ECP__MATH_generate_bitcoin_address_from_mpk( $this->electrum_mpk, $next_key_index );
			$ret_info_array = $this->getreceivedbyaddress_info( $addresses );
			$total_new_keys_generated++;

			if ( $ret_info_array['balance'] === false ) {
				$status = 'unknown';
			} elseif ( $ret_info_array['balance'] == 0 ) { // Newly generated address with freshly checked zero balance is unused and will be assigned.
				$status = 'unused';
			} else { // Generated address that was already used to receive money.
				$status = 'used';
			}

			$funds_received                 = ( $ret_info_array['balance'] === false ) ? -1 : $ret_info_array['balance'];
			$received_funds_checked_at_time = ( $ret_info_array['balance'] === false ) ? 0 : time();

			$ret = $this->db_insert_new_address( $addresses, $status, $funds_received, $received_funds_checked_at_time, $next_key_index );

			$next_key_index++;

			if ( $ret_info_array['balance'] === false ) {
				$blockchains_api_failures ++;
				if ( $blockchains_api_failures >= $this->ecp_settings['max_blockchains_api_failures'] ) {
					// Allow no more than 3 contigious blockchains API failures. After which return error reply.
					return $this->make_return_address( 'error', $ret_info_array['message'], $ret_info_array['host_reply_raw'] );
				}
			} elseif ( $ret_info_array['balance'] == 0 ) {
					// Update DB with balance and timestamp, mark address as 'assigned' and return this address as clean.
					break;
			}

			if ( $total_new_keys_generated >= $this->ecp_settings['max_unusable_generated_addresses'] ) {
				// Stop it after generating of 20 unproductive addresses.
				// Something is wrong. Possibly old merchant's wallet (with many used addresses) is used for new installation. - For this case 'starting_index_for_new_btc_addresses'
				// needs to be proper set to high value.
				return $this->make_return_address( 'error', "Problem: Generated '$total_new_keys_generated' addresses and none were found to be unused. Possibly old merchant's wallet (with many used addresses) is used for new installation. If that is the case - 'starting_index_for_new_btc_addresses' needs to be proper set to high value", '' );
			}
		} while ( true );

		// Here only in case of clean address.
		return $this->make_return_address( 'success', '', '', $addresses );
	}

	public function get_available_providers() {
		// this defines the providers priorities
		// first provider in the array is checked first
		// if it fails, we move on to the next one
		$providers_class = array( 'BTCComAPI', 'BCHSVExplorer', 'BlockdozerAPI', 'BlockExplorerAPI', 'TokenViewAPI' );

		$providers = array();
		foreach ( $providers_class as $provider ) {
			$temp_p = new $provider( $this->get_bitcoin_variant() );
			if ( $temp_p->is_active() ) {
				$providers[] = $temp_p;
			}
		}

		return $providers;
	}
	// ===========================================================================
	//
	public function getreceivedbyaddress_info( $address_array ) {
		$providers      = $this->get_available_providers();
		$funds_received = false;
		foreach ( $providers as $provider ) {
			$funds_received = $provider->get_funds_received( $address_array );
			if ( is_numeric( $funds_received ) ) {
				break;
			}
		}
		$funds_received_numeric = $funds_received;
		if ( is_numeric( $funds_received ) ) {
			$funds_received = sprintf( '%.8f', $funds_received / 100000000.0 );
		}

		if ( is_numeric( $funds_received ) ) {
			$ret_info_array = array(
				'result'         => 'success',
				'message'        => '',
				'host_reply_raw' => '',
				'balance'        => $funds_received,
			);
		} else {
			$ret_info_array = array(
				'result'         => 'error',
				'message'        => 'API failure. Erratic replies.',
				'host_reply_raw' => '' . $funds_received . '',
				'balance'        => false,
			);
		}

		return $ret_info_array;
	}
	// ===========================================================================
}


// ===========================================================================
// ===========================================================================
// To accomodate for multiple MPK's and allowed key limits per MPK
function ECP__get_next_available_mpk( $ecp_settings = false ) {
	if ( ! $ecp_settings ) {
		$ecp_settings = ecp__get_settings();
	}

	return @$this->ecp_settings['electrum_mpks'][0];
}
// ===========================================================================
// ===========================================================================
// Function makes sure that returned value is valid array
function ECP_unserialize_address_meta( $flat_address_meta ) {
	$unserialized = @unserialize( $flat_address_meta );
	if ( is_array( $unserialized ) ) {
		return $unserialized;
	}
	return array();
}
// ===========================================================================
// ===========================================================================
// Function makes sure that value is ready to be stored in DB
function ECP_serialize_address_meta( $address_meta_arr ) {
	return ECP__safe_string_escape( serialize( $address_meta_arr ) );
}
// ===========================================================================
// ===========================================================================
/*
$address_request_array = array (
  'btc_address'            => '1xxxxxxx',
  'required_confirmations' => '6',
  'api_timeout'						 => 10,
  );

$ret_info_array = array (
  'result'                      => 'success',
  'message'                     => "",
  'host_reply_raw'              => "",
  'balance'                     => false == error, else - balance
  );
*/

// ===========================================================================
// ===========================================================================
/*
  Get web page contents
*/
function ECP__file_get_contents( $url, $timeout = 60 ) {
	
	$response = wp_remote_get( $url, $timeout );
	$resp_code = wp_remote_retrieve_response_code( $response );
	$content = wp_remote_retrieve_body( $response );

	if ( ! $err && $resp_code == 200 ) {
		return trim( $content );
	} else {
		return false;
	}
}
// ===========================================================================
// ===========================================================================
function ECP__object_to_array( $object ) {
	if ( ! is_object( $object ) && ! is_array( $object ) ) {
		return $object;
	}
	return array_map( 'ECP__object_to_array', (array) $object );
}
// ===========================================================================
// ===========================================================================
// Credits: http://www.php.net/manual/en/function.mysql-real-escape-string.php#100854
function ECP__safe_string_escape( $str = '' ) {
	$len          = strlen( $str );
	$escapeCount  = 0;
	$targetString = '';
	for ( $offset = 0; $offset < $len; $offset++ ) {
		switch ( $c = $str{$offset} ) {
			case "'":
				// Escapes this quote only if its not preceded by an unescaped backslash
				if ( $escapeCount % 2 == 0 ) {
					$targetString .= '\\';
				}
				 $escapeCount   = 0;
				 $targetString .= $c;
				break;
			case '"':
				// Escapes this quote only if its not preceded by an unescaped backslash
				if ( $escapeCount % 2 == 0 ) {
					$targetString .= '\\';
				}
				 $escapeCount   = 0;
				 $targetString .= $c;
				break;
			case '\\':
				 $escapeCount++;
				 $targetString .= $c;
				break;
			default:
				 $escapeCount   = 0;
				 $targetString .= $c;
		}
	}
	return $targetString;
}
// ===========================================================================
// ===========================================================================
// Syntax:
// ECP__log_event (__FILE__, __LINE__, "Hi!");
// ECP__log_event (__FILE__, __LINE__, "Hi!", "/..");
// ECP__log_event (__FILE__, __LINE__, "Hi!", "", "another_log.php");
function ECP__log_event( $filename, $linenum, $message, $prepend_path = '', $log_file_name = '__log.php' ) {
	$log_filename   = dirname( __FILE__ ) . $prepend_path . '/' . $log_file_name;
	$logfile_header = "<?php exit(':-)'); ?>\n" . '/* =============== P2P Electronic Cash Payments LOG file =============== */' . "\r\n";
	$logfile_tail   = "\r\nEND";

	// Delete too long logfiles.
	// if (@file_exists ($log_filename) && filesize($log_filename)>1000000)
	// unlink ($log_filename);
	$filename = basename( $filename );

	if ( @file_exists( $log_filename ) ) {
		// 'r+' non destructive R/W mode.
		$fhandle = @fopen( $log_filename, 'r+' );
		if ( $fhandle ) {
			@fseek( $fhandle, -strlen( $logfile_tail ), SEEK_END );
		}
	} else {
		$fhandle = @fopen( $log_filename, 'w' );
		if ( $fhandle ) {
			@fwrite( $fhandle, $logfile_header );
		}
	}

	if ( $fhandle ) {
		@fwrite( $fhandle, "\r\n// " . $_SERVER['REMOTE_ADDR'] . '(' . $_SERVER['REMOTE_PORT'] . ')' . ' -> ' . date( 'Y-m-d, G:i:s T' ) . '|' . ECP_VERSION . '/' . "|$filename($linenum)|: " . $message . $logfile_tail );
		@fclose( $fhandle );
	}
}
// ===========================================================================
// ===========================================================================
function ECP__send_email( $email_to, $email_from, $subject, $plain_body ) {
	$message = "
      <html>
      <head>
      <title>$subject</title>
      </head>
      <body>" . $plain_body . '
      </body>
      </html>
      ';

	// To send HTML mail, the Content-type header must be set
	$headers  = 'MIME-Version: 1.0' . "\r\n";
	$headers .= 'Content-type: text/html; charset=iso-8859-1' . "\r\n";

	// Additional headers
	$headers .= 'From: ' . $email_from . "\r\n";    // "From: Birthday Reminder <birthday@example.com>" . "\r\n";

	// Mail it
	$ret_code = @mail( $email_to, $subject, $message, $headers );

	return $ret_code;
}
// ===========================================================================
