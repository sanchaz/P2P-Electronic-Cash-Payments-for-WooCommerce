=== P2P Electronic Cash Payments for WooCommerce ===
Contributors: sanchaz, cryptartica
Donation address: https://cryptartica.com ( Buy yourself some Bitcoin Cash/SV Merchandise :) )
Tags: bitcoin cash, bitcoin cash wordpress plugin, bitcoin cash plugin, bitcoin cash payments, accept bitcoin cash, bch, bitcoin sv, bitcoin sv wordpress plugin, bitcoin sv plugin, bitcoin sv payments, accept bitcoin sv, bsv
Requires at least: Wordpress 4.9.9
Tested up to: Wordpress 5.1.1
Requires PHP: 5.6.40
Stable tag: trunk
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

== Brought to you by Cryptartica ==

P2P Electronic Cash Payments for WooCommerce is a Wordpress plugin that allows you to accept Bitcoin Cash and Bitcoin SV at WooCommerce-powered online stores.

== Description ==

Your online store must use WooCommerce platform (free wordpress plugin).
Once you installed and activated WooCommerce, you may install and activate P2P Electronic Cash Payments for WooCommerce.

If you encounter any problems, please open an issue.

= Features =

* Bitcoin Cash and Bitcoin SV payment gateways.
* Zero fees and no commissions for bitcoin cash/sv payments processing from any third party.
* Accept payment directly into your personal Electron Cash/SV wallet.
* No middleman for payments.
* Accept payment in bitcoin cash for physical and digital downloadable products.
* Individual configurations for each gateway, allowing you to customise each one according to  market conditions.
** Automatic conversion to bitcoin cash/sv via realtime exchange rate feed and calculations.
** Ability to set exchange rate calculation multiplier to compensate for any possible losses due to bank conversions and funds transfer fees.
** Ability to set the amount of time for which to cache the exchange rate, allowing you to react faster to market volatily.
* Store wide configurations such as automatically marking paid orders as completed and Privacy Mode (allows the reuse or not of addresses when orders are not completed).
* Support for APIs requiring accounts, eg. CoinMarketCap and Coinlib.

== Installation ==

1. Clone the git repo or download the zip and extract.  Move 'p2p-electronic-cash-payments-for-woocommerce' dir to /wp-content/plugins/
2. Install "P2P Electronic Cash Payments for WooCommerce" plugin just like any other Wordpress plugin.
3. Activate.


== Screenshots ==

1. Settings
![Settings](screenshots/1.png?raw=true)
2. Advanced Settings
![Advanced Settings](screenshots/2.png?raw=true)
3. Woocommerce Payment methods
![Woocommerce Payment methods](screenshots/3.png?raw=true)
4. Woocommerce Payments methods Bitcoin Cash Settings
![Woocommerce Payments methods Bitcoin Cash Settings](screenshots/4.png?raw=true)
5. Woocommerce Payments methods Bitcoin SV Settings
![Woocommerce Payments methods Bitcoin SV Settings](/screenshots/5.png?raw=true)

== Remove plugin ==

1. Deactivate plugin through the 'Plugins' menu in WordPress
2. Delete plugin through the 'Plugins' menu in WordPress


== Supporters ==

Previously contributed to Bitcoin/Bitcoin Cash/Bitcoin SV Payments for Woocommerce

* sanchaz: https://sanchaz.net
* mboyd1:  https://github.com/mboyd1
* Yifu Guo: http://bitsyn.com/
* Bitcoin Grants: http://bitcoingrant.org/
* Chris Savery: https://github.com/bkkcoins/misc
* lowcostego: http://wordpress.org/support/profile/lowcostego
* WebDesZ: http://wordpress.org/support/profile/webdesz
* ninjastik: http://wordpress.org/support/profile/ninjastik
* timbowhite: https://github.com/timbowhite
* devlinfox: http://wordpress.org/support/profile/devlinfox


== Changelog ==

= 1.00 =
* Refactored and rewrote Bitcoin Cash Payments for Woocommerce to allow for multiple Bitcoin blockchain gateways (currently supported Cash and SV).
* Exchange rate APIs, used to get up to date exchange rates, are now fully rewritten allowing to easily plugin new APIs when needed, by extending the ExchangeRateAPI.
* Blockchain APIs, used to retrieve the balance of addresses generated for payments, are now fully rewritten allowing to easily plugin new APIs when needed, by extending the BlockchainAPI.
* Moved settings to individual gateways in order to allow for more configuration flexibility.

== Frequently Asked Questions ==

Are my keys kept on my server? No! No Private keys are ever stored on your server, the plugin does not need to know about them. So if you do ever get hacked there are no keys to steal, your cryptocurrency is safe. The plugin makes use of HD Master Public Key.

Does this use any payment processor? No! This uses your electrum/electron Master Public Keys, the crytocurrency goes straight from your customer's wallet to your wallet.

Are there any fees? No! This is P2P Electron Cash Payments, from your customer to you, with no middle man, no fees are ever taken at any point, nor will they ever be.

Can I contribute? Sure! Go ahead and make a pull request. Clearly explain what the change does, what's the intended result and why it's required and if it changes any existing behaviour.

== Roadmap ==

* Enable/Disable and select order of preference for APIs used to fetch the exchange rate and check address balance.

== Upgrade Notice ==

None


