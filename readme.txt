=== ART LMS ===
Contributors: artbashlykov
Tags: lms, elearning, payments, digital products, checkout
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.14.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

A lightweight LMS for selling digital products with automated access delivery and payment processing.

== Description ==

ART LMS helps you sell digital learning materials and automatically grant access after successful payment.

Main features:

* Payment buttons and a customizable checkout page
* Automatic material delivery after payment
* Customer account area
* Multiple payment gateway support
* Sales and order statistics in the admin area

== External services ==

This plugin can connect to third-party payment providers to process orders. You choose which gateway to enable in the plugin settings; no payment data is sent until you configure a live gateway.

When a customer pays, the plugin may send order-related data to the selected provider, including:

* Customer email address
* Customer name (if collected on the checkout form)
* Order amount and currency
* Order reference / payment label used to match the payment with the order

Supported payment gateways:

* **YooKassa** — payments are processed on YooKassa servers. See [YooKassa privacy policy](https://yookassa.ru/legal).
* **YooMoney** — payments are processed on YooMoney servers. See [YooMoney terms](https://yoomoney.ru/page?id=525652).
* **Prodamus** — payments are processed on Prodamus servers. See [Prodamus website](https://prodamus.ru/).
* **Plisio** — cryptocurrency payments are processed on Plisio servers. See [Plisio website](https://plisio.net/).

The plugin also includes a **Test** gateway for development; it does not contact external payment services.

Payment providers may send payment status notifications (webhooks) back to your WordPress site so the plugin can mark orders as paid and grant access to purchased materials.

== Installation ==

1. Upload the `art-lms` folder to `/wp-content/plugins/` or install the plugin through the WordPress admin.
2. Activate the plugin on the Plugins screen.
3. Open the ART LMS section in the admin and configure pages, payments, and materials.

== Frequently Asked Questions ==

= Do I need third-party services? =

To accept live payments, configure one of the supported payment gateways in the plugin settings. The built-in test gateway works without external services and is intended for development and testing only.

= What data does the plugin store? =

ART LMS stores order and access data in custom database tables on your WordPress site. This typically includes order status, amount, currency, customer email, name, phone (if enabled on the checkout form), payment gateway name, and timestamps. Access records link users or guest email addresses to purchased materials.

= Does the plugin send email? =

Yes. Order and access notifications are sent using the WordPress `wp_mail()` function and your site's mail configuration (SMTP plugin, hosting mail service, etc.). Email content is defined in the plugin settings.

= What user data is shared with payment gateways? =

When a customer completes checkout with a live payment gateway enabled, the plugin sends the data required to create a payment (such as email, name, order amount, and an order reference) to the provider you configured. Each gateway processes the payment on its own servers. See the **External services** section above for details.

= Does the plugin remove its data when uninstalled? =

By default, no. If you enable **Delete all plugin data when uninstalling ART LMS** in the general plugin settings and then delete the plugin from the Plugins screen, the plugin removes its custom database tables, settings, materials, payment buttons, order/access records, plugin-specific user meta, and the `art_lms_customer` role. WordPress pages you selected in the settings are not deleted.

== Changelog ==

= 2.14.4 =
* Plugin Check fixes: readme, statistics SQL, performance tweaks, and security hardening.
* Optional complete data removal on uninstall (settings checkbox + uninstall.php).
