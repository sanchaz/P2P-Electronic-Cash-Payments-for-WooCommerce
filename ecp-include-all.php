<?php
// ---------------------------------------------------------------------------
// Global definitions
if ( ! defined( 'ECP_PLUGIN_NAME' ) ) {
	define( 'ECP_VERSION', '1.00' );

	// -----------------------------------------------
	define( 'ECP_SETTINGS_NAME', 'ECP-Settings' );
	define( 'ECP_PLUGIN_NAME', 'P2P Electronic Cash Payments for WooCommerce' );


	// i18n plugin domain for language files
	define( 'ECP_I18N_DOMAIN', 'ecp' );

	if ( extension_loaded( 'gmp' ) && ! defined( 'USE_EXT' ) ) {
		define( 'USE_EXT', 'GMP' );
	} elseif ( extension_loaded( 'bcmath' ) && ! defined( 'USE_EXT' ) ) {
		define( 'USE_EXT', 'BCMATH' );
	}
}
// ---------------------------------------------------------------------------
// ------------------------------------------
// This loads necessary modules and selects best math library
require_once dirname( __FILE__ ) . '/libs/util/bcmath_Utils.php';
require_once dirname( __FILE__ ) . '/libs/util/gmp_Utils.php';
require_once dirname( __FILE__ ) . '/libs/CurveFp.php';
require_once dirname( __FILE__ ) . '/libs/Point.php';
require_once dirname( __FILE__ ) . '/libs/NumberTheory.php';
require_once dirname( __FILE__ ) . '/libs/ElectrumHelper.php';

require_once dirname( __FILE__ ) . '/ecp-cron.php';
require_once dirname( __FILE__ ) . '/ecp-mpkgen.php';
require_once dirname( __FILE__ ) . '/ecp-utils.php';
require_once dirname( __FILE__ ) . '/classes/class-table.php';
require_once dirname( __FILE__ ) . '/ecp-admin.php';
require_once dirname( __FILE__ ) . '/ecp-render-settings.php';
require_once dirname( __FILE__ ) . '/ecp-bitcoin-exchange-rate-apis.php';
require_once dirname( __FILE__ ) . '/ecp-bitcoin-gateway.php';
require_once dirname( __FILE__ ) . '/ecp-bitcoin-blockchain-apis.php';



// Load cashaddr libs
require_once dirname( __FILE__ ) . '/libs/cashaddr/Base32.php';
require_once dirname( __FILE__ ) . '/libs/cashaddr/CashAddress.php';
require_once dirname( __FILE__ ) . '/libs/cashaddr/Exception/Base32Exception.php';
require_once dirname( __FILE__ ) . '/libs/cashaddr/Exception/CashAddressException.php';
require_once dirname( __FILE__ ) . '/libs/cashaddr/Exception/InvalidChecksumException.php';
