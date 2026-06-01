=== Hosted Card Payments for WooCommerce ===
Contributors: anojdinuranga
Tags: woocommerce, payment gateway, cybersource, secure acceptance
Requires at least: 6.3
Tested up to: 6.8
Requires PHP: 8.0
WC requires at least: 7.0
WC tested up to: 9.8
Stable tag: 2.0.0
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

WooCommerce payment gateway for CyberSource Secure Acceptance (Hosted Checkout). Works with any CyberSource-backed acquirer.

== Description ==

Adds a CyberSource Secure Acceptance payment method to WooCommerce checkout.
The customer is redirected to the CyberSource-hosted card-entry page, and the signed
response is verified (HMAC-SHA256) before the order is marked paid.

This is a standard, reusable WooCommerce gateway: on a verified ACCEPT it calls
WooCommerce's own `payment_complete()`, so WooCommerce handles order status, stock
reduction, confirmation emails and clearing the cart. No custom database tables are used.

= Security =

* Every outgoing request is signed; every response signature is verified with `hash_equals()`.
* The response amount and currency are re-checked against the order (defence in depth).
* The secret key never leaves the server and is never written to logs.
* High-Performance Order Storage (HPOS) and Cart/Checkout Blocks compatible.

= For developers =

Filters: `hcp_wc_request_fields`, `hcp_wc_endpoint`, `hcp_wc_locale`.
Actions: `hcp_wc_payment_complete`, `hcp_wc_payment_failed`.

Source and contribution guide: https://github.com/anojdinuranga/hosted-card-payments-for-woocommerce

== Installation ==

1. Upload the `hosted-card-payments-for-woocommerce` folder to `/wp-content/plugins/`, or install the ZIP via Plugins > Add New > Upload.
2. Activate the plugin through the 'Plugins' menu (WooCommerce must be active).
3. Go to WooCommerce > Settings > Payments > Hosted Card Payments.
4. Enter your Profile ID, Access Key and Secret Key, choose Test or Production, and Enable.
5. In the CyberSource Business Center, allow the response/receipt URL shown on the settings
   screen (it sends customers back to `/wc-api/hosted_card_payments`).

Your own credentials come from: Business Center > Payment Configuration > Secure Acceptance
Settings > (your profile). Never commit them to source control.

== Frequently Asked Questions ==

= Where do I get the Profile ID, Access Key and Secret Key? =

From your CyberSource Business Center Secure Acceptance profile. The Profile ID is NOT your
merchant/organization ID.

= Does it support testing? =

Yes. Select the "Test (sandbox)" environment and use CyberSource test cards, e.g.
Visa 4111 1111 1111 1111, any future expiry, CVV 123.

== Changelog ==

= 2.0.0 =
* Rebrand and open-source release under GPL-3.0-or-later.
* Requires PHP 8.0+, WordPress 6.3+, WooCommerce 7.0+.
* Declared HPOS and Cart/Checkout Blocks compatibility; added `Requires Plugins` header.
* Security: response amount/currency re-verified against the order; output escaping hardened.
* Added developer filters/actions and optional WooCommerce debug logging.
* Removed all hardcoded credentials from the distribution.

= 1.0.0 =
* Initial release. Secure Acceptance hosted-checkout flow with signed request + verified
  response and standard WooCommerce order handling via payment_complete().
