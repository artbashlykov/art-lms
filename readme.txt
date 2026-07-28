=== ART LMS ===
Contributors: artbashlykov
Tags: lms, elearning, payments, digital products, checkout
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 2.17.28
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

= 2.17.28 =
* Password page: email-only copy (no “login”); redirect logged-in users away from request/confirmation screens.

= 2.17.27 =
* Fix: infinite recursion (memory exhausted) when loading login/password page settings after adding the custom password page.

= 2.17.26 =
* Password-reset email: remove the "Login: {login}" line from the default template (email-only sites).

= 2.17.25 =
* Custom password page (request reset + set new password), enabled with the custom login page; design inherited from login; reset links from email and {установить_пароль} go to the custom page.

= 2.17.24 =
* Emails: custom password-reset template for ART LMS customers (HTML + plugin sender), configurable under Form settings → Emails.

= 2.17.23 =
* Purchase email: `{установить_пароль}` sends the generated password for brand-new accounts, and a set-password link for existing users.

= 2.17.22 =
* Orders: bulk select and delete orders from the admin list.

= 2.17.21 =
* Payment buttons: fix false red validation on production (notices hidden until save attempt; live updates before meta box init; MutationObserver for late Gutenberg mount).

= 2.17.20 =
* Payment buttons: fix materials validation still showing after a material is added (jQuery data-attr quirk; detect list rows + hidden inputs).
* Payment buttons / orders: price fields are plain text inputs (no number spinners).

= 2.17.19 =
* Payment buttons: fix stuck title/materials validation (read title from DOM/iframe, detect materials via data-material-id, force-hide notices with inline display).

= 2.17.18 =
* Payment buttons: fix required-field notices not clearing (read fields by name, not duplicate #id; stop pushing empty meta during validation; material picker event delegation).

= 2.17.17 =
* Payment buttons: clear required-field highlights reliably when typing (duplicate meta-box copies + stronger notice hide CSS).

= 2.17.16 =
* Protected media: fix /art-lms-media/{id}/ 404 when rewrite rules are missing or stale (parse_request fallback, register on activation, flush on version bump).

= 2.17.15 =
* Materials: show "Edit material" in the WordPress admin bar on the frontend when viewing a material (administrators).

= 2.17.14 =
* Orders: fix buyer lookup when creating a new order (REST route registered outside admin-only init).
* Orders: improve user search by login, email, and display name (including Cyrillic logins).

= 2.17.13 =
* Prodamus: fix test mode checkbox not saving as disabled (hidden field + sanitize).
* YooKassa: fix 54-FZ receipts checkbox not saving as disabled (same pattern).

= 2.17.12 =
* Payment buttons: block save and publish until title, product name, price, and at least one material are filled in.
* Payment button editor: red inline validation notices and snackbar when required fields are missing.
* Server-side REST validation for required payment button fields on save.

= 2.17.11 =
* Settings: fix yes/no checkboxes flipping to "on" after save across login, general, email, checkout confirmation, and gateway options (WordPress double sanitize + !empty("no")).
* Checkout form: fix phone and other field toggles saving incorrectly (same root cause).

= 2.17.10 =
* Payment gateways: fix all gateways turning on when saving a single gateway page (double sanitize treated stored "no" as enabled).

= 2.17.9 =
* Payment gateways: enable/disable only when saving the gateways list (removed instant AJAX toggle).
* Payment gateways: fix status saving and preserve enabled/disabled state on the single gateway settings page.
* Custom login: enqueue login styles via wp_enqueue_style for Plugin Check compliance.

= 2.17.8 =
* Checkout form settings: unchecking "Show field" clears and disables "Required".
* Checkout form settings: fix save when preview was open (nested form broke the settings form).
* Checkout form settings: explicit checkbox values so field toggles save correctly on first submit.
* General settings: fix "Delete plugin data on uninstall" saving on the first submit.

= 2.17.7 =
* Payment gateways: disable config fields when a gateway is off so the browser does not block saving (all gateways).
* Payment gateways: document that URL fields must use input type text, not url.

= 2.17.6 =
* Admin: payment gateways moved to a separate menu item «Платежные шлюзы»; menu order updated.
* Payment gateways: auto-save when enabling or disabling a gateway.
* Payment gateways: default gateway list shows only enabled gateways.
* Payment gateways: fix gateway activation on first save from the list page.
* Payment gateways: allow disabling Prodamus without filling the payment form URL.

= 2.17.5 =
* Custom login: guests opening the account page redirect straight to the custom login URL on the first visit (no intermediate wp-login.php).
* Custom login: login URL filter and wp-login.php redirect register earlier on plugins_loaded.
* Custom login: disable full-page cache for wp-login.php when custom login is enabled.

= 2.17.4 =
* Payment gateways: default order is Test, YooMoney, Prodamus, YooKassa, Plisio (one-time migration for existing sites).
* Checkout: product prices moved to a separate line below the product name.

= 2.17.3 =
* Database: create orders/access tables automatically on plugin load when missing (no re-activation required after install or update).
* Database: repair schema migrations when columns are missing despite a stored schema version.
* Database: backfill unique payment_label values for legacy orders stored as an empty string.
* Checkout: retry order insert after ensuring tables/schema when the first insert fails.

= 2.17.2 =
* Checkout field settings: added «Preview on site» button to the form preview panel.
* Checkout: fixed order creation when payment_label collided in the database.
* Checkout: auto-select the only enabled payment gateway when no default is configured.
* Checkout rate limit: email cooldown reduced to 5 seconds; attempts count only after a successful order; disabled for administrators.

= 2.17.1 =
* Payment status block stretches to the full width of the page content area.

= 2.17.0 =
* Quick create: account and payment-check pages contain only the LMS block, without a duplicate H1 heading.
* Quick create: default success page title is «Проверка оплаты».
* Admin menu: section separators use a consistent color in all states (no hover-only visibility).
* Removed unused legacy migration and backfill code unrelated to payments.

= 2.16.9 =
* General settings: «Перейти» page link appears immediately after quick create or when selecting a page in the dropdown.
* Quick create ignores plugin template pages that were moved to trash and creates a fresh page instead.

= 2.16.8 =
* Default page slugs for quick create: cabinet (account) and payment-check (success).
* Account block: materials section title hidden by default to avoid duplicating the page heading.
* Checkout: phone field disabled by default; form card shadow on the frontend.
* Payment button block: hide compare price enabled by default when price is hidden.
* Admin: checkout consent settings table scrolls on narrow screens; ART LMS menu separators visible in all admin color schemes.
* Release build script always writes art-lms.zip to the system temp directory.

= 2.16.7 =
* Custom login page: «Back to site» link below the form (links to the site home page).
* Plugin description updated in the main plugin header.

= 2.16.6 =
* Account block: vertical padding resets when the container border is hidden.
* Account material titles keep the same link color on hover, focus, and visited states.

= 2.16.5 =
* Custom login page: styles load correctly on the standalone template; fixed blank screen after login when redirect_to is empty.
* Material page: title and «Back to account» link above content; theme page title hidden on material pages.
* Account area: mobile padding fixes and list alignment override for art-theme entry-content styles.

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
