# Adeptix Payment Module for PrestaShop

Accept direct on-chain crypto payments (USDT/USDC) and hosted card/bank/wallet checkouts through
[Adeptix](https://adeptix.app), from one PrestaShop module.

Full API reference: **https://docs.adeptix.app**

## What this adds

Two payment options at checkout (both from the same module, following PrestaShop's own
[payment module conventions](https://devdocs.prestashop-project.org/9/modules/payment/) — the
"External" scenario for hosted checkout, the "Offline" scenario for crypto):

- **Card / Bank / Wallet (Adeptix)** — redirects the customer to a hosted checkout via whichever
  provider you configure (e.g. `stripe`). The order is created immediately in a dedicated
  "Awaiting Adeptix payment" status, then moved to "Payment accepted" automatically when Adeptix's
  webhook confirms payment.
- **Crypto (USDT/USDC via Adeptix)** — direct on-chain payment on BSC, Polygon, or Tron. The order
  is created immediately, and the order-confirmation page shows the deposit address and exact
  amount to send; the order moves to "Payment accepted" automatically once the deposit is matched.

## Install

1. Zip this module's folder (`adeptix/`) and upload it via **Modules → Module Manager → Upload a
   module**, or copy it directly into `modules/adeptix/`. The folder **must** be named `adeptix`
   (PrestaShop requires it to match the module name), so put this repository's contents in an
   `adeptix/` folder first — e.g. `git clone https://github.com/Adeptix-app/adeptix-prestashop.git adeptix`.
2. Install and configure it from the Module Manager: set your **API key** (from your Adeptix
   dashboard's API Keys page), your **webhook secret** (from Settings), the **provider ID** (e.g.
   `stripe`), and the crypto **chain**/**token**.
3. The module's configuration page shows the exact webhook URL to register in your Adeptix
   dashboard's Settings page.
4. Enable both payment options for the currencies you accept, under the module's own settings.

## How it works

Both flows build on the [official Adeptix PHP SDK](https://github.com/Adeptix-app/adeptix-php)
(bundled in `vendor/`, not a separate install step) — see `controllers/front/validation.php` (card/
bank/wallet), `controllers/front/crypto.php` (crypto), and `controllers/front/webhook.php`. Order
matching uses the PrestaShop **cart id** as the `order_ref` sent to Adeptix (the order itself
doesn't exist yet at the moment the payment/payment-request is created), and `Order::getIdByCartId()`
resolves it back to the real order when the webhook arrives.

Crypto orders store their deposit address/amount/expiry in a small dedicated table
(`adeptix_crypto_order`, created on install) so the order-confirmation page can display it and the
webhook has nothing extra to look up beyond the order itself.

## Requirements

- PHP 8.0+
- PrestaShop 1.7+ (developed against PrestaShop 9's current payment-module API)

## Verification note

This module was built directly against PrestaShop's own official payment-module documentation and
its reference example module (`PrestaShop/paymentexample` on GitHub) rather than guessed at, and
every PHP file passes `php -l`. Unlike the Adeptix WooCommerce plugin, it was **not**
exercised against a real running PrestaShop install (PrestaShop 9 is a much heavier Symfony
application to stand up locally than WordPress+WooCommerce) — review and test a real checkout/
webhook flow in a staging store before using this in production.

## License

MIT
