<?php
defined( 'ABSPATH' ) or die( 'Bitcoin is for all!' );

// Include everything
require dirname( __FILE__ ) . '/ecp-include-all.php';

// ===========================================================================
function ECP__render_general_settings_page() {
	ECP__render_settings_page( 'general' );
}
function ECP__render_advanced_settings_page() {
	 ECP__render_settings_page( 'advanced' );
}
// ===========================================================================
define( 'ECP_FORM_SETTINGS', 'ecp-submit-form-settings' );
define( 'ECP_FORM_API_KEY', 'ecp-submit-form-api-key' );
// ===========================================================================
function ecp__get_plugin_name_version_edition() {
	$return_data = '<h2 style="border-bottom:1px solid #DDD;padding-bottom:10px;margin-bottom:20px;">' .
			ECP_PLUGIN_NAME . ', version: <span style="color:#EE0000;">' .
			ECP_VERSION . '</span> ' .
		  '</h2>';

	return $return_data;
}
// ===========================================================================
function ECP__render_settings_page( $menu_page_name ) {
	if ( ! current_user_can( 'administrator' ) ) {
		wp_die( 'You are wandering... get back on course.' );
	}
	$ecp_settings = ecp__get_settings();

	if ( isset( $_POST['button_update_ecp_settings'] ) ) {
		check_admin_referer( ECP_FORM_SETTINGS );
		ecp__update_settings();
		echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings updated!
</div>
HHHH;
	} elseif ( isset( $_POST['button_reset_ecp_settings'] ) ) {
		check_admin_referer( ECP_FORM_SETTINGS );
		ECP__reset_all_settings( false );
		echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
All settings reverted to all defaults
</div>
HHHH;
	} elseif ( isset( $_POST['button_reset_partial_ecp_settings'] ) ) {
		check_admin_referer( ECP_FORM_SETTINGS );
		ECP__reset_partial_settings( false );
		echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
Settings on this page reverted to defaults
</div>
HHHH;
	} elseif ( isset( $_POST['button_update_api_keys'] ) ) {
		check_admin_referer( ECP_FORM_API_KEY );
		ECP__update_api_key();
		echo <<<HHHH
<div align="center" style="background-color:#FFFFE0;padding:5px;font-size:120%;border: 1px solid #E6DB55;margin:5px;border-radius:3px;">
API Keys updated!
</div>
HHHH;
	}

	echo '<div class="wrap">';

	switch ( $menu_page_name ) {
		case 'general':
			echo ecp__get_plugin_name_version_edition();
			ECP__render_general_settings_page_html();
			break;

		case 'advanced':
			echo ecp__get_plugin_name_version_edition();
			ECP__render_advanced_settings_page_html();
			break;

		default:
			break;
	}

	echo '</div>'; // wrap
}
// ===========================================================================
// ===========================================================================
function ECP__render_general_settings_page_html() {
	 $ecp_settings = ecp__get_settings(); ?>

	<form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	  <?php wp_nonce_field( ECP_FORM_SETTINGS ); ?>
	  <p class="submit">
		<input type="submit" class="button-primary"    name="button_update_ecp_settings"        value="<?php _e( 'Save Changes' ); ?>"             />
		<input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_ecp_settings" value="<?php _e( 'Reset settings' ); ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
	  </p>
	  <table class="form-table">


		<tr valign="top">
		  <th scope="row">Delete all plugin-specific settings, database tables and data on uninstall:</th>
		  <td>
			<input type="hidden" name="delete_db_tables_on_uninstall" value="0" /><input type="checkbox" name="delete_db_tables_on_uninstall" value="1" 
			<?php
			if ( $ecp_settings['delete_db_tables_on_uninstall'] ) {
														  echo 'checked="checked"';
			}
			?>
													   />
			<p class="description">If checked - all plugin-specific settings, database tables and data will be removed from WordPress database upon plugin uninstall (but not upon deactivation or upgrade).</p>
		  </td>
		</tr>

		<tr valign="top">
			<th scope="row">Extreme privacy mode enabled?</th>
			<td>


			  <select name="reuse_expired_addresses" class="select">
				<option 
				<?php
				if ( $ecp_settings['reuse_expired_addresses'] ) {
					echo 'selected="selected"'; }
				?>
				value="1">No</option>
				<option 
				<?php
				if ( ! $ecp_settings['reuse_expired_addresses'] ) {
					echo 'selected="selected"'; }
				?>
				value="0">Yes (default)</option>
			  </select>

			  <p class="description">
				<b>No</b> - will allow to recycle bitcoin cash addresses that been generated for each placed order but never received any payments. The drawback of this approach is that potential snoop can generate many fake (never paid for) orders to discover sequence of bitcoin cash addresses that belongs to the wallet of this store and then track down sales through blockchain analysis. The advantage of this option is that it very efficiently reuses empty (zero-balance) bitcoin cash addresses within the wallet, allowing only 1 sale per address without growing the wallet size (Electron Cash "gap" value).
				<br />
				<b>Yes</b> (default, recommended) - This will guarantee to generate unique bitcoin cash address for every order (real, accidental or fake). This option will provide the most anonymity and privacy to the store owner's wallet. The drawback is that it will likely leave a number of addresses within the wallet never used (and hence will require setting very high 'gap limit' within the Electron Cash wallet much sooner).
				<br />It is recommended to regenerate new wallet after number of used bitcoin cash addresses reaches 1000. Wallets with very high gap limits (>1000) are very slow to sync with blockchain and they put an extra load on the network. <br />
				Extreme privacy mode offers the best anonymity and privacy to the store albeit with the drawbacks of potentially flooding your Electron Cash wallet with expired and zero-balance addresses.<br />
			  </p>
			</td>
		</tr>

		<tr valign="top">
		  <th scope="row">Auto-complete paid orders:</th>
		  <td>
			<input type="hidden" name="autocomplete_paid_orders" value="0" /><input type="checkbox" name="autocomplete_paid_orders" value="1" 
			<?php
			if ( $ecp_settings['autocomplete_paid_orders'] ) {
					echo 'checked="checked"';
			}
			?>
				 />
			<p class="description">If checked - fully paid order will be marked as 'completed' and '<i>Your order is complete</i>' email will be immediately delivered to customer.
				<br />If unchecked: store admin will need to mark order as completed manually - assuming extra time needed to ship physical product after payment is received.
				<br />Note: virtual/downloadable products will automatically complete upon receiving full payment (so this setting does not have effect in this case).
			</p>
		  </td>
		</tr>

		<tr valign="top">
			<th scope="row">Cron job information:</th>
			<td>
			  <p class="description">
				Cron job will take care of all regular bitcoin cash payment processing tasks, like checking if payments are made and automatically completing the orders.<br />
				<br />
				<div style="background-color:#FFC;color:#2A2;display:inline-block;">DISABLE_WP_CRON is: <?php echo ( defined( 'DISABLE_WP_CRON' ) && constant( 'DISABLE_WP_CRON' ) ? 'true' : 'false' ); ?></div>
				<br />
				<br />
				If DISABLE_WP_CRON is true then this assumes the Cron job is driven by the website hosting system/server (usually via CPanel). <br />
				It pre generates addresses, so the checkout experience for your users is faster. <br />
			  </p>
			</td>
		</tr>

	  </table>

	  <p class="submit">
		  <input type="submit" class="button-primary"    name="button_update_ecp_settings"        value="<?php _e( 'Save Changes' ); ?>"             />
		  <input type="submit" class="button-secondary"  style="color:red;" name="button_reset_partial_ecp_settings" value="<?php _e( 'Reset settings' ); ?>" onClick="return confirm('Are you sure you want to reset settings on this page?');" />
	  </p>
	</form>
	<?php
}
// ===========================================================================
// ===========================================================================
function ECP__render_advanced_settings_page_html() {
	$api_key = ecp__get_settings()['api']['key'];
	?>

  <p style="text-align:center;"><h3>API Keys</h3></p>
  <p>These are keys needed for communicating with service's APIs. Each API listed here will list whether keys are free to get and whether it works without a key.</p>
  <p>We strongly recommend you sign up to all the services that include a free plan and that do not work without an API Key.</p>

  <form method="post" action="<?php echo $_SERVER['REQUEST_URI']; ?>">
	<?php wp_nonce_field( ECP_FORM_API_KEY ); ?>
	<p class="submit">
	  <input type="submit" class="button-primary" name="button_update_api_keys" value="<?php _e( 'Save Changes' ); ?>"/>
	</p>
	<table class="form-table">

	  <tr valign="top">
		<th scope="row">Coinmarket Cap</th>
		<td>
		  <input type="text" style="width:50%;" placeholder="API Key" name="api[key][coinmarketcap]" value="<?php echo $api_key['coinmarketcap']; ?>">
		</td>
	  </tr>
	  <tr>
		<td>
			<div>Free Plan: <span style="color:green">Yes</span></div>
			<div>Works without Key: <span style="color:red">No</span></div>
		</td>
		<td>
		  <a href="https://pro.coinmarketcap.com/signup" target="_blank">Sign Up</a>
		</td>
	  </tr>

	  <tr valign="top">
		<th scope="row">Coinlib</th>
		<td>
		  <input type="text" style="width:50%;" placeholder="API Key" name="api[key][coinlib]" value="<?php echo $api_key['coinlib']; ?>">
		</td>
	  </tr>
	  <tr>
		<td>
			<div>Free Plan: <span style="color:green">Yes</span></div>
			<div>Works without Key: <span style="color:red">No</span></div>
		</td>
		<td>
		  <a href="https://coinlib.io/" target="_blank">Sign Up</a>
		</td>
	  </tr>

	</table>
	<p class="submit">
	  <input type="submit" class="button-primary" name="button_update_api_keys" value="<?php _e( 'Save Changes' ); ?>"/>
	</p>
  </form>

	<?php
}
// ===========================================================================
