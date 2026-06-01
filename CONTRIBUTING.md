# Contributing

Thanks for your interest in improving **Hosted Card Payments for WooCommerce**! This is a community,
open-source project — contributions of all sizes are welcome.

## Ground rules

- **Never commit secrets.** No access keys, secret keys, profile IDs, or real merchant data
  in code, tests, fixtures, screenshots, or commit messages. If a secret is ever exposed,
  rotate it in the CyberSource Business Center immediately.
- Keep the gateway small and auditable. Security-sensitive code (signing/verification) should
  stay easy to read.
- Match the existing WordPress coding style (tabs for indentation, Yoda conditions, `__()` for
  all user-facing strings with the `hosted-card-payments-for-woocommerce` text domain).

## Getting set up

1. A local WordPress + WooCommerce site (e.g. [`wp-env`](https://developer.wordpress.org/block-editor/reference-guides/packages/packages-env/) or LocalWP).
2. Symlink or copy the plugin folder into `wp-content/plugins/`.
3. Create a **test** Secure Acceptance profile in the CyberSource Business Center and use the
   sandbox environment + test cards.

## Coding standards

Run [WordPress Coding Standards](https://github.com/WordPress/WordPress-Coding-Standards) via PHPCS:

```bash
composer global require wp-coding-standards/wpcs --dev
phpcs --standard=WordPress hosted-card-payments-for-woocommerce.php
```

## Pull requests

1. Fork and create a feature branch (`feat/...` or `fix/...`).
2. Make focused commits with clear messages.
3. Describe **what** changed and **why**, and how you tested it (sandbox transaction, etc.).
4. For any change touching signing/verification, explain the security reasoning.

## Reporting security issues

Please **do not** open a public issue for vulnerabilities. Email the maintainer via
https://www.anojdinuranga.com/ with details and reproduction steps. You'll get credit in the
changelog once a fix ships (unless you prefer to stay anonymous).

## License

By contributing, you agree your contributions are licensed under the project's
[GPL-3.0-or-later](LICENSE) license.
