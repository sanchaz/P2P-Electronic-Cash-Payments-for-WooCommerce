<?php

defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );

// APIs to retrieve data for exchange rates
// All APIs should extend from ExchangeRateAPI
// Should implement the following:
// * get_supported_variants
// * get_supported_exchange_rate_types
// According to the get_supported_exchange_rate_types
// the following should be overriden as needed
// * get_exchange_rate_vwap
// * get_exchange_rate_realtime
// If the API needs an API key, then the
// following should also be overriden:
// * __construct
// * is_active
// See Class Coinmarketcap for an example
abstract class ExchangeRateAPI {

	protected $variant_in_use;
	protected $exchange_rate_api_timeout_secs;

	public function __construct( $variant_in_use ) {
		$this->variant_in_use                 = strtolower( $variant_in_use );
		$this->exchange_rate_api_timeout_secs = ecp__get_settings()['exchange_rate_api_timeout_secs'];
	}

	abstract protected function get_supported_variants();

	protected function is_variant_supported() {
		 return in_array( $this->variant_in_use, $this->get_supported_variants() );
	}

	public function is_active() {
		if ( $this->is_variant_supported() ) {
			return true;
		}
		return false;
	}

	abstract protected function get_supported_exchange_rate_types();

	// type can be vwap or realtime
	// can have both or only one
	public function has_exchange_rate_type( $type ) {
		return in_array( $type, $this->get_supported_exchange_rate_types() );
	}

	// returns array of available exchange rate types
	public function exchange_rate_types_available() {
		return $this->get_supported_exchange_rate_types();
	}

	// return false is not available
	public function get_exchange_rate_vwap() {
		return false;
	}

	// return false is not available
	public function get_exchange_rate_realtime() {
		return false;
	}
}

class Bitpay extends ExchangeRateAPI {

	private static $supported_bitcoin_variant = array( 'bch' );
	private static $exchange_rate_types       = array( 'realtime' );

	protected function get_supported_variants() {
		return self::$supported_bitcoin_variant;
	}

	protected function get_supported_exchange_rate_types() {
		return self::$exchange_rate_types;
	}

	public function get_exchange_rate_realtime() {
		$source_url = 'https://bitpay.com/api/rates/BCH/' . get_woocommerce_currency();
		$result     = @ECP__file_get_contents( $source_url, false, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( @$rate_obj['code'] == get_woocommerce_currency() && $rate_obj['rate'] ) {
			return $rate_obj['rate'];   // Only realtime rate is available
		}

		return false;
	}
}

class Coinmarketcap extends ExchangeRateAPI {

	private static $supported_bitcoin_variant = array( 'bch', 'bsv' );
	private static $exchange_rate_types       = array( 'realtime' );

	private $is_active = false;
	private $api_key   = false;

	protected function get_supported_variants() {
		return self::$supported_bitcoin_variant;
	}

	protected function get_supported_exchange_rate_types() {
		return self::$exchange_rate_types;
	}

	public function __construct( $variant_in_use ) {
		parent::__construct( $variant_in_use );
		$ecp_settings  = ecp__get_settings();
		$this->api_key = @$ecp_settings['api']['key']['coinmarketcap'];
		if ( $this->api_key ) {
			$this->is_active = true;
		}
	}

	public function is_active() {
		return parent::is_active() && $this->is_active;
	}

	// return false is not available
	public function get_exchange_rate_realtime() {
		$source_url = 'https://pro-api.coinmarketcap.com/v1/cryptocurrency/quotes/latest?CMC_PRO_API_KEY=' . $this->api_key . '&symbol=' . strtoupper( $this->variant_in_use ) . '&convert=' . strtoupper( get_woocommerce_currency() );
		$result     = @ECP__file_get_contents( $source_url, false, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( $rate_obj['data'][ strtoupper( $this->variant_in_use ) ]['quote'][ strtoupper( get_woocommerce_currency() ) ]['price'] ) {
			return $rate_obj['data'][ strtoupper( $this->variant_in_use ) ]['quote'][ strtoupper( get_woocommerce_currency() ) ]['price'];
		}

		return false;
	}
}

class Coinlib extends ExchangeRateAPI {

	private static $supported_bitcoin_variant = array( 'bch', 'bsv' );
	private static $exchange_rate_types       = array( 'realtime' );

	private $is_active = false;
	private $api_key   = false;

	protected function get_supported_variants() {
		return self::$supported_bitcoin_variant;
	}

	protected function get_supported_exchange_rate_types() {
		return self::$exchange_rate_types;
	}

	public function __construct( $variant_in_use ) {
		parent::__construct( $variant_in_use );
		$ecp_settings  = ecp__get_settings();
		$this->api_key = @$ecp_settings['api']['key']['coinlib'];
		if ( $this->api_key ) {
			$this->is_active = true;
		}
	}

	private function get_symbol() {
		switch ( $this->variant_in_use ) {
			case 'bch':
				return 'BCH';
				break;
			case 'bsv':
				return 'BSV';
				break;
			default:
				return 'none';
				break;
		}
	}

	public function get_exchange_rate_realtime() {
		$source_url = 'https://coinlib.io/api/v1/coin?key=' . $this->api_key . '&pref=' . strtoupper( get_woocommerce_currency() ) . '&symbol=' . $this->get_symbol();
		$result     = @ECP__file_get_contents( $source_url, false, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( strcasecmp( $rate_obj['symbol'], $this->variant_in_use ) != 0 ) {
			return false;
		}

		if ( $rate_obj['price'] ) {
			return $rate_obj['price'];
		}

		return false;
	}
}

class BitcoinAverage extends ExchangeRateAPI {

	private static $supported_bitcoin_variant = array( 'bch' );
	private static $exchange_rate_types       = array( 'vwap', 'realtime' );

	protected function get_supported_variants() {
		return self::$supported_bitcoin_variant;
	}

	protected function get_supported_exchange_rate_types() {
		return self::$exchange_rate_types;
	}

	private function get_exchange_rate( $rate_type ) {
		$source_url = 'https://apiv2.bitcoinaverage.com/indices/global/ticker/short?crypto=' . strtoupper( $this->variant_in_use ) . '&fiat=' . get_woocommerce_currency();
		$result     = @ECP__file_get_contents( $source_url, false, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		if ( ! is_array( $rate_obj ) ) {
			return false;
		}

		$json_root = strtoupper( $this->variant_in_use ) . strtoupper( get_woocommerce_currency() );

		return @$rate_obj[ $json_root ];
	}

	public function get_exchange_rate_vwap() {
		return $this->get_exchange_rate( 'vwap' )['averages']['day'];
	}

	public function get_exchange_rate_realtime() {
		return $this->get_exchange_rate( 'realtime' )['last'];
	}
}

class Coingecko extends ExchangeRateAPI {

	private static $supported_bitcoin_variant = array( 'bch', 'bsv' );
	private static $exchange_rate_types       = array( 'realtime' );

	protected function get_supported_variants() {
		return self::$supported_bitcoin_variant;
	}

	protected function get_supported_exchange_rate_types() {
		return self::$exchange_rate_types;
	}

	private function get_variant_url_part() {
		switch ( $this->variant_in_use ) {
			case 'bch':
				return 'bitcoin-cash';
				break;
			case 'bsv':
				return 'bitcoin-cash-sv';
				break;
			default:
				break;
		}
	}

	public function get_exchange_rate_realtime() {
		$source_url = 'https://api.coingecko.com/api/v3/simple/price?ids=' . $this->get_variant_url_part() . '&vs_currencies=' . get_woocommerce_currency();
		$result     = @ECP__file_get_contents( $source_url, false, $this->exchange_rate_api_timeout_secs );

		$rate_obj = @json_decode( trim( $result ), true );

		$currency_code_tolower = strtolower( get_woocommerce_currency() );

		if ( $rate_obj[ $this->get_variant_url_part() ][ $currency_code_tolower ] ) {
			return $rate_obj[ $this->get_variant_url_part() ][ $currency_code_tolower ];
		}

		return false;
	}
}


