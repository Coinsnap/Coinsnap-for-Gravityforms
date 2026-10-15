=== Bitcoin payment for Gravity Forms ===
Contributors: coinsnap
Tags: bitcoin, lightning, gravity forms, payments, btcpay
Requires at least: 5.2
Tested up to: 7.0.6
Requires PHP: 7.4
Stable tag: 1.3.6
License: GPL2
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Accept Bitcoin and Lightning payments in Gravity Forms for donations, products, bookings, downloads and other payment-driven workflows.

== Description ==

= Add Bitcoin and Lightning payments to Gravity Forms =

Coinsnap for Gravity Forms adds Bitcoin as a payment option to existing Gravity Forms workflows.

Use Gravity Forms as a lightweight Bitcoin checkout for donations, services, registrations, appointments, deposits, downloads, physical products and other one-time payments — without running a full e-commerce system.

Payments can be made with **Bitcoin via Lightning or on-chain** using Coinsnap or your own BTCPay Server.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

= Key features =

* Accept **Bitcoin and Lightning payments** in Gravity Forms
* Support for **Lightning and Bitcoin on-chain**
* Add Bitcoin payments to multiple Gravity Forms
* Suitable for products, donations, bookings, registrations, downloads and service payments
* Bitcoin can be offered alongside existing payment gateways
* Support for **Coinsnap** and **BTCPay Server**
* BTCPay Server setup wizard
* Configurable payment amount per Gravity Forms feed
* Configurable payment-status mapping
* Custom redirect after payment
* Optional **Bitcoin discount**
* No own Lightning node required when using Coinsnap
* Non-custodial / self-custody payment model
* Optional bank settlement through supported Coinsnap partners
* Open source and free to install

= Typical use cases =

Use Coinsnap for Gravity Forms for:

* donation forms
* physical product orders
* digital downloads
* paid registrations
* appointments and consultations
* booking deposits
* event payments
* paid support or contact requests
* membership-related payments
* micropayment workflows
* simple checkout flows without WooCommerce

= How it works =

1. Install and activate Gravity Forms.
2. Install and activate Coinsnap for Gravity Forms.
3. Go to **Forms → Settings → Coinsnap**.
4. Select Coinsnap or BTCPay Server.
5. Enter the required Store ID and API credentials.
6. Configure the after-payment redirect and payment-status mapping.
7. Create or edit a Gravity Form.
8. Open the form's **Settings → Coinsnap** section.
9. Add a Coinsnap feed and configure the payment amount.
10. Publish the form and make a test Bitcoin payment.

= Coinsnap or BTCPay Server =

The add-on supports:

* **Coinsnap** — accept Bitcoin without operating your own Bitcoin or Lightning payment infrastructure.
* **BTCPay Server** — connect your own self-hosted BTCPay Server.

= Gravity Forms integration =

Coinsnap works as a Gravity Forms payment add-on.

Once the global payment provider is configured, Bitcoin payments can be enabled independently for forms by adding a Coinsnap feed.

The feed can define transaction type and payment amount, making the integration suitable for different payment workflows.

= Payment status mapping =

The integration uses the payment states **Expired**, **Processing** and **Settled**.

These can be mapped to the corresponding Gravity Forms payment statuses. For most installations, the default mapping is recommended.

= Bitcoin discount =

The current Coinsnap Gravity Forms product supports an optional **Bitcoin discount** to encourage visitors to choose Bitcoin as their payment method.

= Settlement =

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be automatically converted and settled to a bank account.

= Fees =

Coinsnap charges a **1% transaction fee**. The fee is handled separately through the merchant's Coinsnap balance and is not deducted from the Bitcoin payment.

= More information =

* Product page: https://coinsnap.io/wp-add-ons/gravity-forms/
* User Guide: https://coinsnap.io/user-guides/gravity-forms-bitcoin-user-guide/
* WordPress Plugin Directory: https://wordpress.org/plugins/coinsnap-for-gravity-forms/
* Demo: https://gravity.coinsnap.org/
* GitHub: https://github.com/Coinsnap/Coinsnap-for-Gravityforms
* Coinsnap Wallet: https://coinsnap.io/wallet/
* Coinsnap API: https://docs.coinsnap.io/
* Coinsnap account: https://app.coinsnap.io/register

== Installation ==

1. Install and activate **Gravity Forms**.
2. In WordPress, go to **Plugins → Add New**.
3. Search for **Bitcoin payment for Gravity Forms**.
4. Install and activate the Coinsnap add-on.
5. Go to **Forms → Settings → Coinsnap**.
6. Select Coinsnap or BTCPay Server and connect the provider.
7. Open the Gravity Form you want to monetize.
8. Add a Coinsnap payment feed.
9. Configure the payment amount and behavior.
10. Make a test Bitcoin payment.

== Frequently Asked Questions ==

= Can Gravity Forms accept Bitcoin payments? =

Yes. Coinsnap for Gravity Forms adds Bitcoin and Lightning payments directly to Gravity Forms.

= Can customers pay with both Lightning and Bitcoin on-chain? =

Yes. The integration supports both Lightning and Bitcoin on-chain.

= Can Bitcoin be offered alongside Stripe or PayPal? =

Yes. Bitcoin can be offered alongside other Gravity Forms payment gateways or used as a standalone payment option.

= Can I use my own BTCPay Server? =

Yes. BTCPay Server is supported as an alternative to Coinsnap.

= Can I use Coinsnap in multiple Gravity Forms? =

Yes. Once configured, Coinsnap feeds can be added to multiple forms.

= Can I set the payment amount per form? =

Yes. Payment configuration including the amount is defined in the Coinsnap feed for the Gravity Form.

= Does the plugin support a Bitcoin discount? =

Yes. The current Coinsnap product documentation includes an optional Bitcoin discount.

= Do I need WooCommerce? =

No. Gravity Forms can be used as a lightweight Bitcoin checkout without a shop system.

= Do I need my own Lightning node? =

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

= Does Coinsnap hold my Bitcoin? =

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

= Can payments be settled to a bank account? =

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be converted and settled to a bank account.

= What does Coinsnap cost? =

Coinsnap charges a 1% transaction fee.

== Screenshots ==

1. Install Bitcoin payment for Gravity Forms from the WordPress Plugin Directory
2. Gravity Forms Coinsnap settings
3. Select Coinsnap or BTCPay Server
4. BTCPay Server setup wizard
5. Configure payment-status mapping
6. Add a Coinsnap feed to a Gravity Form
7. Configure payment amount and transaction type
8. Gravity Forms payment form
9. Bitcoin and Lightning payment QR code
10. Successful payment and Gravity Forms entry

== Changelog ==

= 1.3.6 :: 2026-05-24 =
* Compatibility with Gravity Forms 2.10.2 is tested.
* Compatibility with WordPress 7.0 is tested.

= 1.3.5 :: 2026-04-07 =
* Updated Coinsnap Invoice client.
* Updated Coinsnap Store client.
* Compatibility with Gravity Forms 2.9.31 is tested.
* Compatibility with WordPress 6.9.4 is tested.

= 1.3.4 :: 2026-03-06 =
* Updated plugin description.
* Compatibility with Gravity Forms 2.9.25 - 2.9.28 is tested.

= 1.3.3 :: 2025-12-29 =
* Compatibility with Gravity Forms 2.9.24 is tested.
* Updated PHP files protection.
* Updated database requests.

= 1.3.2 :: 2025-12-04 =
* Updated Coinsnap Store client data handling.
* Compatibility with WordPress 6.9 is tested.

= 1.3.1 :: 2025-10-15 =
* Added handling for currencies not supported by BTCPay Server.
* Added custom redirect after payment.
* Fixed BTCPay Server webhook handling.

= 1.3.0 :: 2025-08-20 =
* Updated BTCPay Server minimum-payment calculations.
* Updated BTCPay Server events and webhook handling.
* Updated Coinsnap WordPress library.
* Added nonce validation to the BTCPay Server setup flow.

= 1.2.1 :: 2025-07-11 =
* Improved admin notices.

= 1.2.0 :: 2025-06-24 =
* Improved BTCPay Server setup callback handling.
* Added webhook data storage and webhook-secret validation.

= 1.1.0 :: 2025-05-29 =
* Added BTCPay Server as a Bitcoin payment gateway.
* Added BTCPay Server setup wizard.
* Added minimum-payment validation.

= 1.0.1 :: 2025-04-29 =
* Compatibility with WordPress 6.8 is tested.

= 1.0.0 :: 2025-04-04 =
* First public release for testing.
