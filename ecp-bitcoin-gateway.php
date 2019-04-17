<?php
defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );


// ---------------------------------------------------------------------------
add_action( 'plugins_loaded', 'ECP__plugins_loaded__load_bitcoin_gateway', 0 );
// ---------------------------------------------------------------------------
// Hook payment gateway into WooCommerce
function ECP__plugins_loaded__load_bitcoin_gateway() {
	if ( ! class_exists( 'WC_Payment_Gateway' ) ) {
		// Nothing happens here is WooCommerce is not loaded
		return;
	}

	// =======================================================================
	/**
	 * Bitcoin Based Blockchain Base Payment Gateway
	 *
	 * Provides a base Payment Gateway for Bitcoin blockchains
	 *
	 * @class       ECP_Bitcoin
	 * @extends     WC_Payment_Gateway
	 */
	abstract class ECP_Bitcoin extends WC_Payment_Gateway {

		private static $exchange_rate_type_options = array(
			'vwap'     => 'vwap',
			'realtime' => 'realtime',
			'best'     => 'best',
		);
		// -------------------------------------------------------------------
		/**
		 * Constructor for the gateway.
		 *
		 * @access public
		 * @return void
		 */
		public function __construct() {
			$this->has_fields    = false;
			$this->id            = $this->get_gateway_id();
			$this->settings_name = $this->get_settings_name();

			// Load the form fields.
			$this->init_form_fields();
			$this->init_settings();

			$this->method_title = $this->get_payment_method_title();
			$this->icon         = $this->get_gateway_icon();

			// Define user set variables
			$this->title = $this->settings['title']; // The title which the user is shown on the checkout – retrieved from the settings which init_settings loads.

			$this->description                    = $this->settings['description'];   // Short description about the gateway which is shown on checkout.
			$this->instructions                   = $this->settings['instructions'];  // Detailed payment instructions for the buyer.
			$this->instructions_multi_payment_str = __( 'You may send payments from multiple accounts to reach the total required.', 'woocommerce' );
			// $this->instructions_single_payment_str = __('You must pay in a single payment in full.', 'woocommerce');
			// Actions
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( $this, 'process_admin_options' ) );
			} else {
				add_action( 'woocommerce_update_options_payment_gateways', array( $this, 'process_admin_options' ) );
			} // hook into this action to save options in the backend

			add_action( 'woocommerce_thankyou_' . $this->id, array( $this, 'thankyou_page' ) ); // hooks into the thank you page after payment

			// Customer Emails
			add_action( 'woocommerce_email_before_order_table', array( $this, 'email_instructions' ), 10, 2 ); // hooks into the email template to show additional details

			// Validate currently set currency for the store. Must be among supported ones.
			if ( ! $this->is_gateway_valid_for_use() ) {
				$this->enabled = false;
			}
		}
		// -------------------------------------------------------------------
		//
		public function get_gateway_icon() {
			return plugins_url( $this->settings['checkout_icon'] ? $this->settings['checkout_icon'] : '/images/btc_buyitnow_32x.png', __FILE__ );    // 32 pixels high
		}

		abstract public function get_payment_method_title();

		abstract public function get_gateway_id();

		abstract public function get_settings_name();

		abstract public function get_bitcoin_variant();

		public function get_settings( $key = false ) {
		}

		public function update_settings( $p2pcash_use_these_settings = false, $also_update_persistent_settings = false ) {
		}

		public function update_individual_ecp_setting( &$ecp_current_setting, $ecp_new_setting ) {
		}

		public function get_next_available_mpk() {
			return @$this->settings['electrum_mpk'];
		}

		public function get_max_unused_addresses_buffer() {
			return $this->settings['max_unused_addresses_buffer'];
		}

		public static function is_valid_mpk( $mpk, &$reason_message ) {
			if ( ! $mpk ) {
				$reason_message = __( 'Please specify Electron Cash  Master Public Key (MPK). <br />To retrieve MPK: launch your electron cash wallet, select: Wallet->Master Public Keys, OR: <br />Preferences->Import/Export->Master Public Key->Show)', 'woocommerce' );
			} elseif ( ! preg_match( '/^[a-f0-9]{128}$/', $mpk ) && ! preg_match( '/^xpub[a-zA-Z0-9]{107}$/', $mpk ) ) {
				$reason_message = __( 'Electron Cash  Master Public Key is invalid. Must be 128 or 111 characters long, consisting of digits and letters.', 'woocommerce' );
			} elseif ( ! extension_loaded( 'gmp' ) && ! extension_loaded( 'bcmath' ) ) {
				$reason_message = __(
					"ERROR: neither 'bcmath' nor 'gmp' math extensions are loaded For Electron Cash wallet options to function. Contact your hosting company and ask them to enable either 'bcmath' or 'gmp' extensions. 'gmp' is preferred (much faster)!
            <br />We recommend <a href='http://hostrum.com/' target='_blank'><b>HOSTRUM</b></a> as the best hosting services provider.",
					'woocommerce'
				);
			} else {
				return true;
			}

			return false;
		}

		// -------------------------------------------------------------------
		/**
		 * Check if this gateway is enabled and available for the store's default currency
		 *
		 * @access public
		 * @return bool
		 */
		public function is_gateway_valid_for_use( &$ret_reason_message = null, &$exchange_rate = 0 ) {
			// ----------------------------------
			// Validate settings
			$mpk = $this->get_next_available_mpk();
			if ( ! $this->is_valid_mpk( $mpk, $ret_reason_message ) ) {
				return false;
			}

			// ----------------------------------
			// ----------------------------------
			// Validate connection to exchange rate services
			$store_currency_code = get_woocommerce_currency();
			if ( $store_currency_code != 'BTC' ) {
				$exchange_rate = $this->get_exchange_rate_per_bitcoin();
				if ( ! $exchange_rate ) {

					// Assemble error message.
					$error_msg           = "ERROR: Cannot determine exchange rates (for '$store_currency_code')! {{{ERROR_MESSAGE}}} Make sure your PHP settings are configured properly and your server can (is allowed to) connect to external WEB services via PHP.";
					$extra_error_message = '';
					$fns                 = array( 'file_get_contents', 'curl_init', 'curl_setopt', 'curl_setopt_array', 'curl_exec' );
					$fns                 = array_filter( $fns, 'ECP__function_not_exists' );
					$extra_error_message = '';
					if ( count( $fns ) ) {
						$extra_error_message = 'The following PHP functions are disabled on your server: ' . implode( ', ', $fns ) . '.';
					}

					$reason_message = str_replace( '{{{ERROR_MESSAGE}}}', $extra_error_message, $error_msg );

					if ( $ret_reason_message !== null ) {
						$ret_reason_message = $reason_message;
					}
					return false;
				}
			}
			// ----------------------------------
			// ----------------------------------
			// NOTE: currenly this check is not performed.
			// Do not limit support with present list of currencies. This was originally created because exchange rate APIs did not support many, but today
			// they do support many more currencies, hence this check is removed for now.
			// Validate currency
			// $currency_code            = get_woocommerce_currency();
			// $supported_currencies_arr = ecp__get_settings ('supported_currencies_arr');
			// if ($currency_code != 'BTC' && !@in_array($currency_code, $supported_currencies_arr))
			// {
			// $reason_message = __("Store currency is set to unsupported value", 'woocommerce') . "('{$currency_code}'). " . __("Valid currencies: ", 'woocommerce') . implode ($supported_currencies_arr, ", ");
			// if ($ret_reason_message !== NULL)
			// $ret_reason_message = $reason_message;
			// return false;
			// }
			return true;
			// ----------------------------------
		}
		// -------------------------------------------------------------------
		abstract public function get_payment_instructions_description();

		abstract public function default_payment_instructions();

		abstract public function get_icon_dir();

		public function get_checkout_icon_options() {

			$icon_options = array();

			$plugin_root = dirname( __FILE__ );
			$icon_dir    = $this->get_icon_dir();
			$icons       = scandir( $plugin_root . $icon_dir );
			foreach ( $icons as $icon ) {
				if ( ! is_file( $plugin_root . $icon_dir . $icon ) ) {
					continue;
				}
				$icon_rel_path = $icon_dir . $icon;
				$icon_url      = plugins_url( $icon_rel_path, __FILE__ );

				$icon_options[ $icon ] = array(
					'url'      => $icon_url,
					'rel_path' => $icon_rel_path,
				);
			}

			return $icon_options;
		}

		public function generate_iconradio_html( $key, $data ) {
			$field_key = $this->get_field_key( $key );
			$defaults  = array(
				'title'             => '',
				'disabled'          => false,
				'class'             => '',
				'css'               => '',
				'placeholder'       => '',
				'type'              => 'text',
				'desc_tip'          => false,
				'description'       => '',
				'custom_attributes' => array(),
				'options'           => array(),
			);

			$data = wp_parse_args( $data, $defaults );

			ob_start();
			?>
			<tr valign="top">
				<th scope="row" class="titledesc">
				<?php echo $this->get_tooltip_html( $data ); ?>
					<label for="<?php echo esc_attr( $field_key ); ?>"><?php echo wp_kses_post( $data['title'] ); ?></label>
				</th>
				<td class="forminp">
					<fieldset>
						<legend class="screen-reader-text"><span><?php echo wp_kses_post( $data['title'] ); ?></span></legend>

					<?php foreach ( (array) $data['options'] as $icon_key => $icon_data ) : ?>
							<input type="radio" class="<?php echo esc_attr( $data['class'] ); ?>" name="<?php echo esc_attr( $field_key ); ?>" id="<?php echo esc_attr( $field_key ); ?>" style="<?php echo esc_attr( $data['css'] ); ?>" <?php disabled( $data['disabled'], true ); ?> <?php echo $this->get_custom_attribute_html( $data ); ?> id="<?php echo esc_attr( $icon_key ); ?>" value="<?php echo esc_attr( $icon_data['rel_path'] ); ?>" <?php checked( $icon_data['rel_path'], esc_attr( $this->get_option( $key ) ) ); ?> />

							<label for="<?php echo esc_attr( $icon_key ); ?>"><img src="<?php echo esc_attr( $icon_data['url'] ); ?>" height="32"></img></label><br />
						<?php endforeach; ?>
						
					<?php echo $this->get_description_html( $data ); ?>
					<?php
					echo "<p class='description'>You can upload new icons for this gateway to: " . str_replace( ABSPATH, '', dirname( __FILE__ ) . $this->get_icon_dir() ) . '<br/>
                                        Make sure to scale the image to a height of 32px.</p>';
					?>
					</fieldset>
				</td>
			</tr>
				<?php

				return ob_get_clean();
		}

		// -------------------------------------------------------------------
		/**
		 * Initialise Gateway Settings Form Fields
		 *
		 * @access public
		 * @return void
		 */
		public function init_form_fields() {
			// This defines the settings we want to show in the admin area.
			// This allows user to customize payment gateway.
			// Add as many as you see fit.
			// See this for more form elements: http://wcdocs.woothemes.com/codex/extending/settings-api/
			// -----------------------------------
			// Assemble currency ticker.
			$store_currency_code = get_woocommerce_currency();
			if ( $store_currency_code == 'BTC' ) {
				$currency_code = 'USD';
			} else {
				$currency_code = $store_currency_code;
			}

			// -----------------------------------
			// Payment instructions
			$payment_instructions = $this->default_payment_instructions();

			$payment_instructions = trim( $payment_instructions );

			$payment_instructions_description = $this->get_payment_instructions_description( $payment_instructions );

			$payment_instructions_description = trim( $payment_instructions_description );
			// -----------------------------------
			$this->form_fields = array(
				'enabled'                              => array(
					'title'   => __( 'Enable/Disable', 'woocommerce' ),
					'type'    => 'checkbox',
					'label'   => __( 'Enable Bitcoin ' . $this->get_bitcoin_variant() . ' Payments', 'woocommerce' ),
					'default' => 'yes',
				),
				'title'                                => array(
					'title'       => __( 'Title', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce' ),
					'default'     => __( 'Bitcoin Payment', 'woocommerce' ),
				),

				'description'                          => array(
					'title'       => __( 'Customer Message', 'woocommerce' ),
					'type'        => 'text',
					'description' => __( 'Initial instructions for the customer at checkout screen', 'woocommerce' ),
					'default'     => __( 'Please proceed to the next screen to see necessary payment details.', 'woocommerce' ),
				),
				'instructions'                         => array(
					'title'       => __( 'Payment Instructions (HTML)', 'woocommerce' ),
					'type'        => 'textarea',
					'description' => $payment_instructions_description,
					'default'     => $payment_instructions,
				),

				'starting_index_for_new_btc_addresses' => array(
					'title'   => __( 'Starting index for addresses (HD Generation)', 'woocommerce' ),
					'type'    => 'number',
					'default' => 2,
				),
				'max_unused_addresses_buffer'          => array(
					'title'       => __( 'Maximum Unused addresses generated', 'woocommerce' ),
					'description' => __( 'Number of pre-generated addresses (This speeds up the checkout process). Only works if DISABLE_WP_CRON or Hard Cron (see general settings) are used.', 'woocommerce' ),
					'type'        => 'number',
					'default'     => 10,
				),
				'cache_exchange_rates_for_minutes'     => array(
					'title'       => __( 'Cache the exchange rate.', 'woocommerce' ),
					'description' => __( 'The amount of time to cache the exchange rate for in minutes.', 'woocommerce' ),
					'type'        => 'number',
					'default'     => 10,
				),
				'electrum_mpk_saved'                   => array(
					'title'       => __( 'All Electrum Master Public Keys you\'ve used previously.', 'woocommerce' ),
					'description' => __( 'Changing this field will have no effect.', 'woocommerce' ),
					'type'        => 'textarea',
					'default'     => '',
				),
				'electrum_mpk'                         => array(
					'title'       => __( 'Electrum Master Public Key in use.', 'woocommerce' ),
					'description' => __( 'Changing this will switch the Master Public Key in use.', 'woocommerce' ),
					'type'        => 'text',
					'default'     => '',
				),
				'exchange_rate_type'                   => array(
					'title'       => __( 'The prefered rate type.', 'woocommerce' ),
					'description' => __( 'Rate type can be vwap (average 24hr), realtime or best (slow!). Selecting vwap or realtime will only act as a preference, whichs means we will always try to get that type of rate first and fallback to the other in case of failure, best (slow!) will query all available providers for both vwap, and realtime and select the best.', 'woocommerce' ),
					'type'        => 'select',
					'default'     => 'vwap',
					'options'     => self::$exchange_rate_type_options,
				),
				'exchange_multiplier'                  => array(
					'title'       => __( 'Multiply the exchnage rate by this value before using it.', 'woocommerce' ),
					'description' => __( 'Use this to hedge for volatility (1.05 or whatever ou feel is safe.) or to give your users a discount by paying with Bitcoin (0.95 or whatever you feel is good).', 'woocommerce' ),
					'default'     => '1.00',
				), // store as string so we don't have issues with floats until it is time to use it
				'checkout_icon'                        => array(
					'title'       => __( 'The icon your users see when choosing the checkout options.', 'woocommerce' ),
					'description' => __( 'The user will see multiple checkout options, each one identified by an icon. This defines the icon for this checkout option.', 'woocommerce' ),
					'type'        => 'iconradio',
					'options'     => $this->get_checkout_icon_options(),
				),
			);
		}

		// This field is never used, we just use it as previous value reference
		public function validate_electrum_mpk_saved_field( $key, $value ) {
			$value = $this->settings['electrum_mpk_saved'];
			return $value;
		}

		public function validate_electrum_mpk_field( $key, $value ) {
			$value      = trim( $value );
			$mpks_array = explode( ',', $this->settings['electrum_mpk_saved'] );
			$reason_message;
			if ( $value && ! in_array( $value, $mpks_array ) && $this->is_valid_mpk( $value, $reason_message ) ) {
				// add to array
				if ( $this->settings['electrum_mpk_saved'] ) {
					$this->settings['electrum_mpk_saved'] .= ',';
				}
				$this->settings['electrum_mpk_saved'] .= $value;
			} elseif ( $value && in_array( $value, $mpks_array ) ) {
				// move things around?
				// remove if not needed
			} else {
				$value = $this->settings['electrum_mpk'];
			}
			return $value;
		}

		// validate exchange rate types here so we can "more safely" avoid
		// checking for exchange rate type at call time time
		public function validate_exchange_rate_type_field( $key, $value ) {
			if ( in_array( $value, self::$exchange_rate_type_options ) ) {
				return $value;
			}

			return $this->settings['exchange_rate_type'];
		}

		/**
		 * Admin Panel Options
		 * - Options for bits like 'title' and availability on a country-by-country basis
		 *
		 * @access public
		 * @return void
		 */
		public function admin_options() {
			$validation_msg = '';
			$exchange_rate  = 0;
			$store_valid    = $this->is_gateway_valid_for_use( $validation_msg, $exchange_rate );
			$currency_code  = get_woocommerce_currency();

			// After defining the options, we need to display them too; thats where this next function comes into play:
			?>
			  <h3><?php _e( 'Bitcoin ' . $this->get_bitcoin_variant() . ' Payment', 'woocommerce' ); ?></h3>
			  <p>
			<?php _e( 'Allows to accept payments in bitcoin ' . $this->get_bitcoin_variant() . '. Bitcoin is peer-to-peer, decentralized digital currency that enables instant payments from anyone to anyone, anywhere in the world.', 'woocommerce' ); ?>
			  </p>
			<?php
			if ( $store_valid ) {
				echo '<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#004400;background-color:#CCFFCC;">' . __( 'Bitcoin ' . $this->get_bitcoin_variant() . ' payment gateway is operational', 'woocommerce' ) . '</p>';
				echo "<p style='border:1px solid #DDD;padding:5px 10px;background-color:#cceeff;'>According to your settings (including multiplier), current calculated rate for 1 Bitcoin " . $this->get_bitcoin_variant() . ' (in ' . $currency_code . ')=' . $exchange_rate . '</p>';
			} else {
				echo '<p style="border:1px solid #DDD;padding:5px 10px;font-weight:bold;color:#EE0000;background-color:#FFFFAA;">' . __( 'Bitcoin payment gateway is not operational (try to re-enter and save Plugin settings): ', 'woocommerce' ) . $validation_msg . '</p>';
			}
			?>
			  <table class="form-table">
			<?php
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			?>
				</table><!--/.form-table-->
			<?php
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		// Hook into admin options saving.
		public function process_admin_options() {
			// Call parent
			parent::process_admin_options();

			return;
		}
		// -------------------------------------------------------------------
		function get_available_providers() {
			// this defines the providers priorities
			// first provider in the array is checked first
			// if it fails, we move on to the next one
			$providers_class = array( 'Bitpay', 'BitcoinAverage', 'Coingecko', 'Coinmarketcap', 'Coinlib' );

			$providers = array();
			foreach ( $providers_class as $provider ) {
				$temp_p = new $provider( $this->get_bitcoin_variant() );
				if ( $temp_p->is_active() ) {
					$providers[] = $temp_p;
				}
			}

			return $providers;
		}

		function get_rate( $exchange_rate_type ) {
			$providers = $this->get_available_providers();
			foreach ( $providers as $provider ) {
				if ( $provider->has_exchange_rate_type( $exchange_rate_type ) ) {
					$rate = call_user_func( array( $provider, 'get_exchange_rate_' . $exchange_rate_type ) );
					if ( $rate ) {
						return $rate;
					}
				}
			}
			return false;
		}

		// this exists to allow overriding at the gateway implementation level
		function get_vwap_rate() {
			return $this->get_rate( 'vwap' );
		}

		// this exists to allow overriding at the gateway implementation level
		function get_realtime_rate() {
			return $this->get_rate( 'realtime' );
		}

		// this exists to allow overriding at the gateway implementation level
		function get_best_rate() {
			$providers = $this->get_available_providers();

			$best_rate = 0;
			foreach ( $providers as $provider ) {
				$types = $provider->exchange_rate_types_available();
				foreach ( $types as $type ) {
					$best_rate = max( $best_rate, call_user_func( array( $provider, 'get_exchange_rate_' . $type ) ) );
				}
			}

			return $best_rate;
		}

		function get_cache() {
			$ecp_settings = ecp__get_settings();
			return @$ecp_settings['exchange_rates'][ get_woocommerce_currency() ][ $this->settings['exchange_rate_type'] ];
		}

		function set_cache( $exchange_rate ) {

			// Save new currency exchange rate info in cache
			ECP__update_cache( $exchange_rate, $exchange_rate_type );
		}

		function get_exchange_rate_per_bitcoin() {
			if ( get_woocommerce_currency() == 'BTC' ) {
				return '1.00';
			}   // 1:1

			$exchange_multiplier = $this->settings['exchange_multiplier'];

			if ( ! $exchange_multiplier ) {
				$exchange_multiplier = 1;
			}

			$current_time       = time();
			$this_currency_info = $this->get_cache();

			if ( $this_currency_info && isset( $this_currency_info['time-last-checked'] ) ) {
				$delta = $current_time - $this_currency_info['time-last-checked'];
				if ( $delta < ( @$this->settings['cache_exchange_rates_for_minutes'] * 60 ) ) {

					// Exchange rates cache hit
					// Use cached value as it is still fresh.
					$final_rate = $this_currency_info['exchange_rate'] / $exchange_multiplier;
					return $final_rate;
				}
			}

			// we can straight up call the function since we've
			// previously validated the exchange rate type
			$exchange_rate = call_user_func( array( $this, 'get_' . $this->settings['exchange_rate_type'] . '_rate' ) );

			if ( $exchange_rate ) {
				return $exchange_rate / $exchange_multiplier;
			}

			// if the call fail on vwap or realtime for back to the other
			switch ( $this->settings['exchange_rate_type'] ) {
				case 'vwap':
					$exchange_rate = call_user_func( array( $this, 'get_realtime_rate' ) );
					break;
				case 'realtime':
					$exchange_rate = call_user_func( array( $this, 'get_vwap_rate' ) );
					break;
				default:
					break;
			}

			if ( $exchange_rate ) {
				return $exchange_rate / $exchange_multiplier;
			}

			return false;
		}

		abstract public function get_electrum_util();

		abstract public function update_order_metadata( $order_id, $ret_info_array);

		// -------------------------------------------------------------------
		/**
		 * Process the payment and return the result
		 *
		 * @access public
		 * @param int $order_id
		 * @return array
		 */
		public function process_payment( $order_id ) {
			$ecp_settings = ecp__get_settings();
			$order        = new WC_Order( $order_id );

			// TODO: Implement CRM features within store admin dashboard
			$order_meta                = array();
			$order_meta['bw_order']    = $order;
			$order_meta['bw_items']    = $order->get_items();
			$order_meta['bw_b_addr']   = $order->get_formatted_billing_address();
			$order_meta['bw_s_addr']   = $order->get_formatted_shipping_address();
			$order_meta['bw_b_email']  = $order->billing_email;
			$order_meta['bw_currency'] = $order->order_currency;
			$order_meta['bw_settings'] = $ecp_settings;
			$order_meta['bw_store']    = plugins_url( '', __FILE__ );

			// -----------------------------------
			// Save bitcoin payment info together with the order.
			// Note: this code must be on top here, as other filters will be called from here and will use these values ...
			//
			// Calculate realtime bitcoin price (if exchange is necessary)
			$exchange_rate = $this->get_exchange_rate_per_bitcoin();
			// $exchange_rate = ECP__get_exchange_rate_per_bitcoin (get_woocommerce_currency(), $this->exchange_rate_retrieval_method, $this->exchange_rate_type);
			if ( ! $exchange_rate ) {
				$msg = 'ERROR: Cannot determine Bitcoin exchange rate. Possible issues: store server does not allow outgoing connections, exchange rate servers are blocking incoming connections or down. ';
				ECP__log_event( __FILE__, __LINE__, $msg );
				exit( '<h2 style="color:red;">' . $msg . '</h2>' );
			}

			$order_total_in_btc = ( $order->get_total() / $exchange_rate );
			if ( get_woocommerce_currency() != 'BTC' ) {
				// Apply exchange rate multiplier only for stores with non-bitcoin default currency.
				$order_total_in_btc = $order_total_in_btc;
			}

			$order_total_in_btc = sprintf( '%.8f', $order_total_in_btc );

			$bitcoins_address = false;
			$bch_cashaddr     = false;

			$order_info =
			array(
				'order_meta'       => $order_meta,
				'order_id'         => $order_id,
				'order_total'      => $order_total_in_btc,  // Order total in BTC
				'order_datetime'   => date( 'Y-m-d H:i:s T' ),
				'requested_by_ip'  => @$_SERVER['REMOTE_ADDR'],
				'requested_by_ua'  => @$_SERVER['HTTP_USER_AGENT'],
				'requested_by_srv' => base64_encode( serialize( $_SERVER ) ),
			);

			$ret_info_array = array();

			// Generate bitcoin address for electron cash wallet provider.
			/*
			$ret_info_array = array (
			   'result'                      => 'success', // OR 'error'
			   'message'                                         => '...',
			   'host_reply_raw'              => '......',
			   'generated_bitcoin_address'   => '18vzABPyVbbia8TDCKDtXJYXcoAFAPk2cj', // or false
			);
			*/
			$ret_info_array = $this->get_electrum_util()->get_bitcoin_address_for_payment__electrum( $order_info );

			if ( $ret_info_array['result'] != 'success' ) {
				$msg = "ERROR: cannot generate bitcoin address for the order: '" . @$ret_info_array['message'] . "'";
				ECP__log_event( __FILE__, __LINE__, $msg );
				exit( '<h2 style="color:blue;">' . $msg . '</h2>' );
			}

			update_post_meta(
				$order_id,             // post id ($order_id)
				'order_total_in_btc',  // meta key
				$order_total_in_btc    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,             // post id ($order_id)
				'bitcoins_paid_total', // meta key
				'0'    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,             // post id ($order_id)
				'bitcoins_refunded',   // meta key
				'0'    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,            // post id ($order_id)
				'exchange_rate',  // meta key
				$exchange_rate    // meta value. If array - will be auto-serialized
			);
			update_post_meta(
				$order_id,                 // post id ($order_id)
				'_incoming_payments',  // meta key. Starts with '_' - hidden from UI.
				array()                    // array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
			);
			update_post_meta(
				$order_id,                 // post id ($order_id)
				'_payment_completed',  // meta key. Starts with '_' - hidden from UI.
				0                  // array (array('datetime'=>'', 'from_addr'=>'', 'amount'=>''),)
			);
			update_post_meta(
				$order_id,       // post id ($order_id)
				'bitcoin_variant',  // meta key
				$this->get_bitcoin_variant()  // meta value. If array - will be auto-serialized
			);
			$this->update_order_metadata( $order_id, $ret_info_array );
			// -----------------------------------
			// The bitcoin gateway does not take payment immediately, but it does need to change the orders status to on-hold
			// (so the store owner knows that bitcoin payment is pending).
			// We also need to tell WooCommerce that it needs to redirect to the thankyou page – this is done with the returned array
			// and the result being a success.
			//
			global $woocommerce;

			// Updating the order status:
			// Mark as on-hold (we're awaiting for bitcoins payment to arrive)
			$order->update_status( 'on-hold', __( 'Awaiting bitcoin ' . $this->get_bitcoin_variant() . ' payment to arrive', 'woocommerce' ) );

			/*
			  ///////////////////////////////////////
			  // timbowhite's suggestion:
			  // -----------------------
			  // Mark as pending (we're awaiting for bitcoins payment to arrive), not 'on-hold' since
			  // woocommerce does not automatically cancel expired on-hold orders. Woocommerce handles holding the stock
			  // for pending orders until order payment is complete.
			  $order->update_status('pending', __('Awaiting bitcoin payment to arrive', 'woocommerce'));

			  // Me: 'pending' does not trigger "Thank you" page and neither email sending. Not sure why.
			  //            Also - I think cancellation of unpaid orders needs to be initiated from cron job, as only we know when order needs to be cancelled,
			  //            by scanning "on-hold" orders through 'assigned_address_expires_in_mins' timeout check.
			  ///////////////////////////////////////
			*/
			// Remove cart
			$woocommerce->cart->empty_cart();

			// Empty awaiting payment session
			unset( $_SESSION['order_awaiting_payment'] );

			// Return thankyou redirect
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1', '<' ) ) {
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, get_permalink( woocommerce_get_page_id( 'thanks' ) ) ) ),
				);
			} else {
				return array(
					'result'   => 'success',
					'redirect' => add_query_arg( 'key', $order->order_key, add_query_arg( 'order', $order_id, $this->get_return_url( $order ) ) ),
				);
			}
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		/**
		 * Output for the order received page.
		 *
		 * @access public
		 * @return void
		 */
		public function thankyou_page( $order_id ) {
			// ECP__thankyou_page is hooked into the "thank you" page and in the simplest case can just echo’s the description.
			// Get order object.
			// http://wcdocs.woothemes.com/apidocs/class-WC_Order.html
			$order = new WC_Order( $order_id );

			$instructions = $this->fill_in_instructions( $order, true );

			echo wpautop( wptexturize( $instructions ) );
		}
		// -------------------------------------------------------------------
		// -------------------------------------------------------------------
		/**
		 * Add content to the WC emails.
		 *
		 * @access public
		 * @param WC_Order $order
		 * @param bool     $sent_to_admin
		 * @return void
		 */
		public function email_instructions( $order, $sent_to_admin ) {
			if ( $sent_to_admin ) {
				return;
			}
			if ( ! in_array( $order->status, array( 'pending', 'on-hold' ), true ) ) {
				return;
			}
			if ( $order->payment_method !== 'bitcoin' ) {
				return;
			}

			$instructions = $this->fill_in_instructions( $order );

			echo wpautop( wptexturize( $instructions ) );
		}
		// -------------------------------------------------------------------
		abstract public function fill_in_instructions( $order, $add_order_note = false);
		// -------------------------------------------------------------------
	}
	// END Class ECP_Bitcoin
	// include all gateways implemented
	require_once 'ecp-bitcoin-gateway-bch.php';
	require_once 'ecp-bitcoin-gateway-bsv.php';

	// =======================================================================
	// -----------------------------------------------------------------------
	// Hook into WooCommerce - add necessary hooks and filters
	add_filter( 'woocommerce_payment_gateways', 'ECP__add_bitcoin_gateway' );

	// Disable unnecessary billing fields.
	// Note: it affects whole store.
	// add_filter ('woocommerce_checkout_fields' ,     'ECP__woocommerce_checkout_fields' );
	add_filter( 'woocommerce_currencies', 'ECP__add_btc_currency' );
	add_filter( 'woocommerce_currency_symbol', 'ECP__add_btc_currency_symbol', 10, 2 );

	// Change [Order] button text on checkout screen.
	// Note: this will affect all payment methods.
	// add_filter ('woocommerce_order_button_text',    'ECP__order_button_text');
	// -----------------------------------------------------------------------
	// =======================================================================
	/**
	 * Add the gateway to WooCommerce
	 *
	 * @access public
	 * @param array $methods
	 * @package
	 * @return array/
	 */
	function ECP__add_bitcoin_gateway( $methods ) {
		// $methods[] = 'ECP_Bitcoin';
		$methods[] = 'ECP_Bitcoin_Cash';
		$methods[] = 'ECP_Bitcoin_SV';
		return $methods;
	}
	// =======================================================================
	// =======================================================================
	// Our hooked in function - $fields is passed via the filter!
	function ECP__woocommerce_checkout_fields( $fields ) {
		unset( $fields['order']['order_comments'] );
		unset( $fields['billing']['billing_first_name'] );
		unset( $fields['billing']['billing_last_name'] );
		unset( $fields['billing']['billing_company'] );
		unset( $fields['billing']['billing_address_1'] );
		unset( $fields['billing']['billing_address_2'] );
		unset( $fields['billing']['billing_city'] );
		unset( $fields['billing']['billing_postcode'] );
		unset( $fields['billing']['billing_country'] );
		unset( $fields['billing']['billing_state'] );
		unset( $fields['billing']['billing_phone'] );
		return $fields;
	}
	// =======================================================================
	// =======================================================================
	function ECP__add_btc_currency( $currencies ) {
		$currencies['BCH'] = __( 'Bitcoin Cash (฿)', 'woocommerce' );
		$currencies['BSV'] = __( 'Bitcoin SV (฿)', 'woocommerce' );
		return $currencies;
	}
	// =======================================================================
	// =======================================================================
	function ECP__add_btc_currency_symbol( $currency_symbol, $currency ) {
		switch ( $currency ) {
			case 'BTC':
			case 'BCH':
			case 'BSV':
				$currency_symbol = '฿';
				break;
		}

		return $currency_symbol;
	}
	// =======================================================================
	// =======================================================================
	function ECP__order_button_text() {
		return 'Continue';
	}
	// =======================================================================
}
// ###########################################################################
// ===========================================================================
function ECP__process_payment_completed_for_order( $order_id, $bitcoins_paid = false ) {
	if ( $bitcoins_paid ) {
		update_post_meta( $order_id, 'bitcoins_paid_total', $bitcoins_paid );
	}

	// Payment completed
	// Make sure this logic is done only once, in case customer keep sending payments :)
	if ( ! get_post_meta( $order_id, '_payment_completed', true ) ) {
		update_post_meta( $order_id, '_payment_completed', '1' );

		ECP__log_event( __FILE__, __LINE__, "Success: order '{$order_id}' paid in full. Processing and notifying customer ..." );

		// Instantiate order object.
		$order = new WC_Order( $order_id );
		$order->add_order_note( __( 'Order paid in full', 'woocommerce' ) );

		$order->payment_complete();

		$ecp_settings = ecp__get_settings();
		if ( $ecp_settings['autocomplete_paid_orders'] ) {
			// Ensure order is completed.
			$order->update_status( 'completed', __( 'Order marked as completed according to Bitcoin Cash plugin settings', 'woocommerce' ) );
		}

		// Notify admin about payment processed
		$email = get_settings( 'admin_email' );
		if ( ! $email ) {
			$email = get_option( 'admin_email' );
		}
		if ( $email ) {
			// Send email from admin to admin
			ECP__send_email(
				$email,
				$email,
				"Full payment received for order ID: '{$order_id}'",
				"Order ID: '{$order_id}' paid in full. <br />Received BCH: '$bitcoins_paid'.<br />Please process and complete order for customer."
			);
		}
	}
}
// ===========================================================================
