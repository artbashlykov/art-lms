=== ART LMS ===
Contributors: artbashlykov
Tags: lms, elearning, payments, digital products, checkout
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.16.4
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

ART LMS stores order and access data in custom database tables on your WordPress site. This typically includes order status, amount, currency, customer email, name, phone (if enabled on the checkout form), payment gateway name, and timestamps. Access records link WordPress user accounts to purchased materials.

= How does access delivery work? =

After a successful payment, ART LMS creates or links a WordPress user account and grants access to the purchased materials. Buyers open materials from the customer account page or from links in the purchase email. Access is tied to the WordPress user account, not to a standalone guest session.

= Are files in the Media Library protected? =

Files embedded in LMS materials are served through a protected download route and blocked on attachment pages when possible. Direct static requests to `/wp-content/uploads/` may still be served by your web server without running PHP on some hosts. Re-save materials after updating if you need to register older attachments for protection.

= How are refunds handled? =

Payment provider refunds and chargebacks are not processed automatically. If an order must be revoked, change the order status in the ART LMS admin area to refunded or cancelled; access will be removed accordingly.

= What about Plisio crypto payments? =

Plisio may send a `mismatch` status when the paid amount is within the tolerance configured in your Plisio account (for example after exchange-rate movement). ART LMS accepts `completed` and `mismatch` callbacks after signature verification. If `source_amount` is present in the callback, it is compared with the order total.

= Does the plugin send email? =

Yes. Order and access notifications are sent using the WordPress `wp_mail()` function and your site's mail configuration (SMTP plugin, hosting mail service, etc.). Email content is defined in the plugin settings.

= What user data is shared with payment gateways? =

When a customer completes checkout with a live payment gateway enabled, the plugin sends the data required to create a payment (such as email, name, order amount, and an order reference) to the provider you configured. Each gateway processes the payment on its own servers. See the **External services** section above for details.

= Does the plugin remove its data when uninstalled? =

By default, no. If you enable **Delete all plugin data when uninstalling ART LMS** in the general plugin settings and then delete the plugin from the Plugins screen, the plugin removes its custom database tables, settings, materials, payment buttons, order/access records, plugin-specific user meta, and the `art_lms_customer` role. WordPress pages you selected in the settings are not deleted.

== Changelog ==

= 2.16.4 =
* GitHub update checker (Plugin Update Checker) with GitHub API User-Agent and release asset art-lms.zip.

= 2.16.3 =
* Removed embedded Plugin Update Checker to comply with WordPress.org guidelines. Use ART Master Install or the WordPress.org plugin directory for updates.

= 2.16.2 =
* GitHub update checker: check for updates and install new versions from the Plugins screen.
* LMS materials are excluded from the WordPress core sitemap.

= 2.16.1 =
* Protected downloads for files embedded in LMS materials.
* Email verification checkout now keeps the selected payment gateway and re-validates the payment button.
* Readme FAQ updated with access, file protection, refunds, and Plisio notes.

= 2.16.0 =
* Custom login page with configurable URL, form texts, button styling, and form design (colors, dimensions, field styles).
* Login settings tab with live preview; collapsible design sections in the admin.
* Redirect logged-in visitors away from the custom login page; Plugin Check fixes for login routing.

= 2.15.0 =
* Delete orders from the admin orders list and order view.
* Checkout form title setting and typography controls in form design settings.
* Documentation panel and partner signup links for payment gateways (Prodamus, YooKassa, YooMoney, Plisio).
* Checkout page style isolation from theme CSS; layout and control fixes on the public checkout form.
* Removed legacy Capitalist gateway cleanup code.

= 2.14.4 =
* Plugin Check fixes: readme, statistics SQL, performance tweaks, and security hardening.
* Optional complete data removal on uninstall (settings checkbox + uninstall.php).
