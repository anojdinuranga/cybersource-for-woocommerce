# Hosted Card Payments for WooCommerce

Hosted Card Payments for WooCommerce is a small WooCommerce payment gateway for
CyberSource Secure Acceptance Hosted Checkout.

Customers are redirected to the hosted card-entry page, so card details are not
collected or stored by WordPress. The plugin signs outgoing requests and verifies
CyberSource response signatures before completing WooCommerce orders.

This is an independent plugin. It is not affiliated with, endorsed by, or
officially maintained by CyberSource, Visa, HSBC, or WooCommerce.

## Features

- CyberSource Secure Acceptance Hosted Checkout integration.
- Test and production endpoint support.
- Signed checkout requests using HMAC-SHA256.
- Response signature verification with `hash_equals()`.
- Amount and currency validation before order completion.
- Standard WooCommerce order handling via `payment_complete()`.
- WooCommerce High-Performance Order Storage (HPOS) compatibility.
- WooCommerce Cart and Checkout Blocks compatibility.
- Optional WooCommerce debug logging without storing secrets or card data.
- Developer filters and actions for extending request fields and payment events.

## Requirements

- WordPress 6.3 or newer.
- WooCommerce 7.0 or newer.
- PHP 8.0 or newer.
- A CyberSource Secure Acceptance profile with Profile ID, Access Key, and
  Secret Key.

## How It Works

```text
WooCommerce checkout
  -> process_payment()
  -> WooCommerce receipt page
  -> auto-submitted signed Secure Acceptance form
  -> customer pays on the CyberSource hosted page
  -> CyberSource posts the result to /wc-api/hosted_card_payments
  -> plugin verifies signature, amount, and currency
  -> WooCommerce completes or fails the order
```

The request and response signature format is:

```text
Base64( HMAC-SHA256( secret_key, data_to_sign ) )
```

`data_to_sign` is built from the comma-separated field names listed in
`signed_field_names`, using the `name=value` values supplied for those fields.

## Installation

### WordPress Admin

1. Download the plugin ZIP.
2. In WordPress admin, go to **Plugins > Add New > Upload Plugin**.
3. Upload the ZIP and activate the plugin.
4. Go to **WooCommerce > Settings > Payments > Hosted Card Payments**.

### Manual Install

Copy the plugin folder to your WordPress plugins directory:

```bash
cp -r hosted-card-payments-for-woocommerce /path/to/wordpress/wp-content/plugins/
```

Then activate **Hosted Card Payments for WooCommerce** from the WordPress
Plugins screen.

## Configuration

In WooCommerce, open:

```text
WooCommerce > Settings > Payments > Hosted Card Payments
```

Configure these fields:

| Setting | Description |
| --- | --- |
| Enable/Disable | Turns the payment method on or off. |
| Title | Payment method title shown at checkout. |
| Description | Checkout description shown to customers. |
| Profile ID | Secure Acceptance Profile ID. This is not your merchant or organization ID. |
| Access Key | Access key for the Secure Acceptance profile. |
| Secret Key | Secret key for signing and verifying messages. Keep this private. |
| Environment | Test or Production Secure Acceptance endpoint. |
| Transaction Type | Sale or Authorization. |
| Debug Log | Optional WooCommerce gateway logging for troubleshooting. |

## CyberSource Setup

In the CyberSource Business Center, open your Secure Acceptance profile and allow
the response or receipt URL shown in the plugin settings.

The URL normally looks like this:

```text
https://example.com/wc-api/hosted_card_payments
```

Your credentials are available from:

```text
Payment Configuration > Secure Acceptance Settings > your profile
```

Never commit real Profile IDs, Access Keys, or Secret Keys to source control.

## Testing

Use the **Test (sandbox)** environment while integrating.

Common CyberSource test card:

```text
Card: 4111 1111 1111 1111
Expiry: any future date
CVV: 123
```

After a test payment, confirm that:

- the order is completed only after a verified `ACCEPT` response;
- failed or tampered responses do not complete the order;
- the order note contains the expected transaction result;
- WooCommerce stock, emails, and cart clearing behave normally.

## Security Notes

- Card data is entered on the hosted payment page, not on your WordPress site.
- The plugin never logs the Secret Key.
- Response signatures are required before accepting a payment result.
- Amount and currency are checked against the WooCommerce order before payment
  completion.
- Debug logs should still be disabled on production sites unless needed for
  troubleshooting.

## Developer Hooks

### Filters

Add or modify fields before the request is signed:

```php
add_filter( 'hcp_wc_request_fields', function ( array $fields, WC_Order $order ) {
	$fields['merchant_defined_data1'] = 'channel:web';
	return $fields;
}, 10, 2 );
```

Override the Secure Acceptance endpoint:

```php
add_filter( 'hcp_wc_endpoint', function ( $url ) {
	return $url;
} );
```

Set the hosted page locale:

```php
add_filter( 'hcp_wc_locale', function ( $locale, WC_Order $order ) {
	return 'en-us';
}, 10, 2 );
```

Any field added through `hcp_wc_request_fields` is automatically included in
`signed_field_names` before signing.

### Actions

Run custom logic after a successful verified payment:

```php
add_action( 'hcp_wc_payment_complete', function ( WC_Order $order, array $params ) {
	// Notify another system or store extra metadata.
}, 10, 2 );
```

Run custom logic after a failed payment response:

```php
add_action( 'hcp_wc_payment_failed', function ( WC_Order $order, array $params ) {
	// Alert support or record failure analytics.
}, 10, 2 );
```

## Debugging

Enable **Debug Log** in the gateway settings, then check:

```text
WooCommerce > Status > Logs
```

Look for logs with source `hosted_card_payments`.

Logs are intended for request and response metadata only. They should not contain
card data or the Secret Key.

## Project Layout

```text
hosted-card-payments-for-woocommerce/
|-- hosted-card-payments-for-woocommerce.php
|-- readme.txt
|-- README.md
|-- CONTRIBUTING.md
|-- LICENSE
|-- assets/
|   |-- icon-128x128.png
|   |-- icon-256x256.png
|   |-- images/
|   |   `-- card-logo.png
|   `-- js/
|       `-- blocks.js
`-- .gitignore
```

## Release Checklist

Before publishing a ZIP or submitting to WordPress.org:

1. Make sure the plugin folder is named `hosted-card-payments-for-woocommerce`.
2. Confirm the main plugin file is `hosted-card-payments-for-woocommerce.php`.
3. Confirm `readme.txt` has the correct stable tag and changelog.
4. Confirm `assets/icon-128x128.png` and `assets/icon-256x256.png` are present.
5. Run a PHP syntax check:

```bash
php -l hosted-card-payments-for-woocommerce.php
```

6. Create the ZIP from the parent directory so the ZIP contains one top-level
   `hosted-card-payments-for-woocommerce/` folder.
7. Test activation and checkout on a clean WordPress + WooCommerce install.

## Contributing

Issues and pull requests are welcome. See [CONTRIBUTING.md](CONTRIBUTING.md).

## License

Released under the [GNU General Public License v3.0 or later](LICENSE).
