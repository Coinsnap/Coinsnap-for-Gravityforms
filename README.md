# Coinsnap for Gravity Forms

![Coinsnap for Gravity Forms](https://resources.coinsnap.org/products/gravityforms/images/cover.png)

**Bitcoin payment add-on for Gravity Forms — accept Bitcoin and Lightning in forms for products, donations, bookings, registrations, downloads and other payment-driven workflows.**

Coinsnap for Gravity Forms adds Bitcoin as a payment option to existing Gravity Forms workflows.

Website owners can use Gravity Forms as a lightweight Bitcoin checkout for donations, services, event registrations, appointments, deposits, downloads, physical products and other one-time payments — without running a full e-commerce system.

Payments can be made with **Bitcoin via Lightning or on-chain** using **Coinsnap** or your own **BTCPay Server**.

Coinsnap is **Bitcoin payment software for merchants** and a brand of **Onlineshop24 d.o.o., Serbia**.

## Key features

- Accept **Bitcoin and Lightning payments** in Gravity Forms
- Support for **Lightning and Bitcoin on-chain**
- Add Bitcoin payments to multiple Gravity Forms
- Suitable for products, donations, bookings, registrations, downloads and service payments
- Works alongside existing Gravity Forms payment gateways
- Support for **Coinsnap** and **BTCPay Server**
- BTCPay Server setup wizard
- Configurable payment amount per Gravity Forms feed
- Configurable payment-status mapping
- Custom redirect after payment
- Optional **Bitcoin discount** to encourage Bitcoin payments
- No own Lightning node required when using Coinsnap
- **Non-custodial / self-custody** payment model
- Optional settlement to a bank account through supported Coinsnap partners
- Open source and free to install

## Current public release

The current WordPress.org release is **1.3.6**.

WordPress.org currently lists:

- WordPress **5.2 or newer**
- PHP **7.4 or newer**
- Tested up to **WordPress 7.0.6**
- Gravity Forms compatibility tested up to **2.10.2**

> **Developer note:** the current GitHub `main` branch still identifies the plugin as version **1.3.5**. The repository code metadata and the public WordPress.org release should be synchronized separately.

## Typical use cases

Coinsnap for Gravity Forms can be used for:

- donation forms
- physical product orders
- digital downloads
- paid registrations
- appointments and consultations
- booking deposits
- event payments
- paid support or contact requests
- membership-related payments
- low-value and micropayment workflows
- simple checkout flows without WooCommerce

## How it works

1. Install and activate **Gravity Forms**.
2. Install and activate **Coinsnap for Gravity Forms**.
3. Open **Forms → Settings → Coinsnap**.
4. Select **Coinsnap** or **BTCPay Server**.
5. Enter the required Store ID and API credentials.
6. Configure the after-payment redirect and payment-status mapping.
7. Create or edit a Gravity Form.
8. Open the form's **Settings → Coinsnap** section.
9. Add a Coinsnap feed and configure the payment amount.
10. Publish the form and make a test payment.

## Coinsnap or BTCPay Server

The add-on supports two payment-provider modes:

- **Coinsnap** — for website owners who want to accept Bitcoin without operating their own Bitcoin or Lightning payment infrastructure.
- **BTCPay Server** — for users who want to connect their own self-hosted BTCPay Server.

## Gravity Forms integration

Coinsnap works as a Gravity Forms payment add-on.

Once the global payment provider is configured, Bitcoin payments can be enabled independently for Gravity Forms by adding a Coinsnap feed to the selected form.

The feed can define the transaction type and payment amount, making the integration suitable for a wide range of form-based payment workflows.

## Payment status mapping

The current Coinsnap user guide documents three payment states used by the integration:

- **Expired**
- **Processing**
- **Settled**

These can be mapped to the corresponding Gravity Forms payment statuses in the Coinsnap settings.

For most installations, the default mapping is recommended.

## Bitcoin discount

The current Coinsnap Gravity Forms product page supports an optional **Bitcoin discount** to encourage visitors to choose Bitcoin as their payment method.

This can be useful when Bitcoin is offered alongside traditional payment methods.

## Settlement

With Coinsnap, payments can be settled to a compatible Bitcoin or Lightning wallet.

One option is the [Coinsnap Wallet](https://coinsnap.io/wallet/).

Coinsnap follows a **non-custodial / self-custody** approach and does not hold merchant Bitcoin.

Depending on the merchant setup and supported partner integrations, Bitcoin payments can also be automatically converted and settled to a bank account.

## Fees

Coinsnap charges a **1% transaction fee**. The fee is handled separately through the merchant's Coinsnap balance and is not deducted from the Bitcoin payment itself.

## Installation

### Install from WordPress.org

1. Install and activate **Gravity Forms**.
2. In WordPress, go to **Plugins → Add New**.
3. Search for **Bitcoin payment for Gravity Forms**.
4. Install and activate the Coinsnap add-on.
5. Go to **Forms → Settings → Coinsnap**.
6. Connect Coinsnap or BTCPay Server.
7. Open the Gravity Form you want to monetize.
8. Add a Coinsnap payment feed.
9. Configure the amount and payment behavior.
10. Make a test Bitcoin payment.

### Install from GitHub

1. Download this repository as a ZIP archive.
2. Upload it through **Plugins → Add New → Upload Plugin**.
3. Install and activate the add-on.
4. Make sure Gravity Forms is installed and active.
5. Configure Coinsnap or BTCPay Server in the Gravity Forms payment settings.

## Resources

- [Coinsnap for Gravity Forms product page](https://coinsnap.io/wp-add-ons/gravity-forms/)
- [Gravity Forms Bitcoin User Guide](https://coinsnap.io/user-guides/gravity-forms-bitcoin-user-guide/)
- [WordPress Plugin Directory](https://wordpress.org/plugins/coinsnap-for-gravity-forms/)
- [Gravity Forms demo](https://gravity.coinsnap.org/)
- [Coinsnap Wallet](https://coinsnap.io/wallet/)
- [Coinsnap API documentation](https://docs.coinsnap.io/)
- [Coinsnap merchant account](https://app.coinsnap.io/register)
- [Telegram community and support](https://t.me/coinsnap)

> **Brand note:** Coinsnap is Bitcoin payment software for merchants and is not the provider of the similarly named coin identifier app "CoinSnap".

## Frequently Asked Questions

### Can Gravity Forms accept Bitcoin payments?

Yes. Coinsnap for Gravity Forms adds Bitcoin and Lightning payments directly to Gravity Forms.

### Can customers pay with both Lightning and Bitcoin on-chain?

Yes. The integration supports both Lightning and Bitcoin on-chain.

### Can Bitcoin be offered alongside Stripe or PayPal?

Yes. Bitcoin can be added alongside other Gravity Forms payment gateways or used as a standalone option.

### Can I use my own BTCPay Server?

Yes. BTCPay Server is supported as an alternative to Coinsnap.

### Can I use Coinsnap in more than one Gravity Form?

Yes. Once the payment provider is configured, you can add Coinsnap feeds to multiple Gravity Forms.

### Can I set the payment amount per form?

Yes. The Coinsnap feed for a Gravity Form includes payment configuration including the amount.

### Does the plugin support Bitcoin discounts?

Yes. The current Coinsnap product page documents an optional Bitcoin discount for Gravity Forms.

### Do I need WooCommerce?

No. Gravity Forms can be used as a lightweight Bitcoin checkout without a shop system.

### Do I need my own Lightning node?

No. When using Coinsnap, no own Lightning node or Bitcoin payment server is required.

### Does Coinsnap hold my Bitcoin?

No. Coinsnap follows a non-custodial / self-custody approach and does not hold merchant Bitcoin.

### Can payments be settled to a bank account?

Depending on the merchant setup and supported partner integrations, Bitcoin payments can be automatically converted and settled to a bank account.

### What does Coinsnap cost?

Coinsnap charges a 1% transaction fee.

## Changelog

### 1.3.6 — 2026-05-24
- Compatibility with Gravity Forms 2.10.2 tested.
- Compatibility with WordPress 7.0 tested.

### 1.3.5 — 2026-04-07
- Updated the Coinsnap Invoice client.
- Updated the Coinsnap Store client.
- Compatibility with Gravity Forms 2.9.31 tested.
- Compatibility with WordPress 6.9.4 tested.

### 1.3.4 — 2026-03-06
- Updated plugin description.
- Compatibility with Gravity Forms 2.9.25–2.9.28 tested.

### 1.3.3 — 2025-12-29
- Compatibility with Gravity Forms 2.9.24 tested.
- Updated PHP file protection.
- Updated database requests.

### 1.3.2 — 2025-12-04
- Updated Coinsnap Store client data handling.
- Compatibility with WordPress 6.9 tested.

### 1.3.1 — 2025-10-15
- Added handling for currencies not supported by BTCPay Server.
- Added custom redirect after payment.
- Fixed BTCPay Server webhook handling.

### 1.3.0 — 2025-08-20
- Updated BTCPay Server minimum-payment calculations.
- Updated BTCPay Server events and webhook handling.
- Updated the Coinsnap WordPress library.
- Added nonce validation to the BTCPay setup flow.

### 1.2.1 — 2025-07-11
- Improved admin notices.

### 1.2.0 — 2025-06-24
- Improved BTCPay Server setup callback handling.
- Added webhook data storage and webhook-secret validation.

### 1.1.0 — 2025-05-29
- Added BTCPay Server as a payment gateway.
- Added BTCPay Server setup wizard.
- Added minimum-payment validation.

### 1.0.1 — 2025-04-29
- Compatibility with WordPress 6.8 tested.

### 1.0.0 — 2025-04-04
- First public release for testing.
