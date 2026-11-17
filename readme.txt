=== Pro Web Design Hold New Subscriptions for WooCommerce ===
Contributors: prowebdeignuk, freemius
Tags: woocommerce, subscriptions, order status, hpos, workflow
Requires at least: 5.8
Tested up to: 7.1
Requires PHP: 7.2
Requires Plugins: woocommerce
Stable tag: 1.5.1
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Hold newly created WooCommerce Subscriptions until the parent order reaches a status you choose, then activate them automatically.

== Description ==

By default, WooCommerce Subscriptions activates a new subscription as soon as checkout completes — even if the store still needs to manually review, verify, or fulfil the order before the customer should actually have access. Customers see "Active" right away, try to use what they paid for, can't, and contact support.

**Pro Web Design Hold New Subscriptions for WooCommerce** keeps a new subscription On-hold (or Pending) until its parent order reaches a status you choose — Completed by default — then switches it to Active and, optionally, emails the customer.

This plugin is an add-on for **WooCommerce Subscriptions**, a separate paid extension sold by WooCommerce. It does nothing without it.

= Key Features =

* Puts new subscriptions on **On-hold** or **Pending** right after checkout, without interfering with payment processing or gateway callbacks
* Automatically activates the subscription once the parent order reaches any of the order statuses you select (defaults to **Completed**)
* Works with new subscriptions only: renewal orders and renewal payments are never touched
* Optionally restrict the hold behaviour to specific payment gateways
* Optional subscription notes and WooCommerce log entries for every action the plugin takes
* Optional customer emails — one when a subscription is placed on hold, one when it's activated
* Full **HPOS** (High-Performance Order Storage) compatibility
* Translation-ready: all texts can be translated at translate.wordpress.org

= Professional version =

Everything above is free. A separate Professional version adds:

* **Send info and activate** — send the customer a note from a saved template and activate the subscription in one click, from the order or the subscription screen
* **Per-product rules** — a different hold status and different activation statuses for individual subscription products
* **Escalation timer** — get notified, or have the subscription activated or cancelled automatically, when it stays on hold longer than the time you set
* **Notifications** — a webhook to Slack, Discord, Mattermost or any URL when a subscription is held, activated or escalated

The Professional version is sold from the plugin's Pricing page in your WordPress admin and comes with a 14-day trial. None of its code is included in this free plugin.

Screenshots, a feature comparison and prices: [Hold New Subscriptions on pro-webdesign.co.uk](https://www.pro-webdesign.co.uk/plugins/hold-new-subscriptions).

= Who Is This For? =

Stores that sell WooCommerce Subscriptions but need a manual review, verification, or fulfilment step before granting access — so subscribers don't see "Active" prematurely and don't reach out to support asking why they can't use something the system already marked as active.

== Installation ==

1. Install the plugin through the WordPress plugins screen, or upload its folder to `/wp-content/plugins/`
2. Activate the plugin through the **Plugins** menu in WordPress
3. Go to **WooCommerce → Hold Subscriptions** to configure the initial hold status, the order statuses that trigger activation, and the optional emails, notes, and logging
4. WooCommerce and the WooCommerce Subscriptions extension (sold separately by WooCommerce) must be active

== Frequently Asked Questions ==

= Do I need WooCommerce Subscriptions? =

Yes. The plugin changes how the WooCommerce Subscriptions extension treats a new subscription, so both WooCommerce and WooCommerce Subscriptions must be installed and active. WooCommerce Subscriptions is a paid extension from WooCommerce and is not included.

= Does this affect renewal orders? =

No. The plugin holds new subscriptions only — it acts on a subscription's first order. Renewal orders and renewal payments are left entirely to WooCommerce Subscriptions.

= Does it work with WooCommerce HPOS? =

Yes. The plugin declares compatibility with WooCommerce's High-Performance Order Storage and uses the subscription's own metadata API throughout, so it works the same whether HPOS is on or off.

= Will this break my payment gateway? =

No. The plugin never changes a subscription's status while the order is still being processed at checkout. The hold is applied only after checkout has fully completed (on the thank-you page or payment-complete event), so scheduled payments and gateway callbacks are unaffected.

= What about bank transfers and other payments that are confirmed later? =

They are held as well. When such a payment is confirmed and the order becomes paid, WooCommerce Subscriptions activates the subscription; the plugin immediately puts it back on hold and keeps it there until the order reaches the status you chose. The customer is not emailed a second time.

One case is not covered: a payment extension that activates the subscription itself, without changing the status of the order.

= Can I limit this to specific payment methods? =

Yes. Enable "Limit by payment gateways" in the settings and choose which gateways the hold logic should apply to. Orders paid through other gateways are left untouched.

= What happens if I select multiple activation statuses? =

The subscription activates as soon as the parent order reaches any one of the statuses you selected.

= Does the plugin send any data anywhere? =

Not unless you agree. The plugin includes the Freemius SDK, which is used to sell and update the Professional version. After activation it asks whether you want to share basic, non-sensitive diagnostic data (WordPress and PHP versions, the plugin version, your site address and admin email). You can skip that question and the plugin works exactly the same; nothing is sent if you skip. Freemius privacy policy: https://freemius.com/privacy/

== Screenshots ==

1. Settings page under WooCommerce → Hold Subscriptions

== Changelog ==

= 1.5.1 =
* Added: a "Settings saved." message after saving the settings.
* Confirmed compatibility with WooCommerce 11.3 (tested on WooCommerce 11.3.0, WooCommerce Subscriptions 9.4.1 and WordPress 7.1.4).
* Changed: the plugin's homepage link now points to the plugin page on pro-webdesign.co.uk.

= 1.5.0 =
* Added: a Professional version is now available (send info and activate, per-product rules, escalation timer, notifications). It is a separate download; this free plugin works as before.
* Added: the Freemius SDK, used for the Professional version's licensing and updates. It asks once, after activation, whether you want to share basic diagnostic data — optional, and nothing is sent if you skip.
* Changed: the plugin's data is now removed on uninstall only when no other edition of the plugin (free or Professional) is still installed, so switching editions does not lose your settings.
* No changes to how subscriptions are held or activated.

= 1.4.6 =
* Removed the bundled translation files and the built-in Russian fallback. Translations are now handled by translate.wordpress.org, as for every plugin in the directory. No functional changes.

= 1.4.5 =
* Removed the "Skip renewal orders" setting. It never changed anything: the plugin has always worked with a subscription's first order only, and renewal orders are now skipped unconditionally.

= 1.4.4 =
* Changed: when the parent order reaches an activation status, the plugin now activates only subscriptions it is holding itself (or has just marked at checkout). A subscription that is on hold for another reason — for example an unpaid renewal — is no longer activated because its original order changed status.
* Added Russian translations of the notes introduced in 1.4.3.

= 1.4.3 =
* Fixed: when a payment was confirmed only after the customer had reached the thank-you page (bank transfer, cheque, gateways that confirm later), the subscription became Active as soon as the order was paid instead of waiting for the order status you chose. It is now kept on hold until that status.
* Fixed: when such an order went straight from unpaid to the chosen status, the "subscription activated" email, the subscription note and the `hnsub_subscription_activated` action were skipped.
* For developers: new action `hnsub_subscription_held_again`.

= 1.4.2 =
* The rename is complete: the two customer emails now have the ids `hnsub_subscription_hold` and `hnsub_subscription_active` (their settings are carried over), their templates are `emails/hnsub-subscription-*.php`, and the flags the plugin keeps on subscriptions are stored as `_hnsub_*` (flags written by earlier versions are still honoured).
* The "subscription activated" email no longer contains wording that only made sense for one particular store.

= 1.4.1 =
* The plugin is now called "Pro Web Design Hold New Subscriptions for WooCommerce"; the text domain follows the new name. No functional changes.

= 1.4.0 =
* Internal names now use the longer `hnsub` prefix (functions, classes, constants, hooks, options), as WordPress.org asks for prefixes of at least four characters. Behaviour is unchanged.
* Settings saved by earlier versions are carried over automatically on update.
* For developers: the old hook names (`hns_subscription_held`, `hns_subscription_activated`, `hns_subscription_options`, `hns_after_settings_page`) still work and now report a deprecation notice; use the `hnsub_` names instead.
* The settings page address changed to `admin.php?page=hnsub-settings` (still under WooCommerce → Hold Subscriptions).
* Uninstalling now also removes the two customer email settings.

= 1.3.5 =
* Internal: bumped "Tested up to" to 7.1 following the WordPress core update.

= 1.3.2 – 1.3.4 =
* Internal maintenance. No change in behaviour.

= 1.3.1 =
* Fixed: Plugin Check errors — missing translators comments on strings with placeholders, and unescaped subscription/order IDs in the email templates (now cast with `absint()`).
* Fixed: Plugin Check warnings — removed the discouraged `load_plugin_textdomain()` call (WordPress auto-loads bundled translations from the Text Domain/Domain Path headers since 4.6), corrected two `phpcs:ignore` comments that weren't actually suppressing the lines they were meant to.
* Internal: `DEVELOPMENT.md` is excluded from the distributed plugin zip.

= 1.3.0 =
* For developers: added the hooks `hns_subscription_options`, `hns_subscription_held` and `hns_subscription_activated` (renamed to `hnsub_…` in 1.4.0). No change in behaviour.

= 1.2.1 =
* Fixed: the duplicate-activation guard used `add_post_meta()`, which reads and writes `wp_postmeta` directly and silently stops working once a store enables WooCommerce HPOS (subscriptions then store their meta in a separate custom table). It now uses the subscription's own metadata API (`get_meta()` / `update_meta_data()` / `save_meta_data()`), matching HPOS and legacy storage alike.
* Added: `FeaturesUtil::declare_compatibility( 'custom_order_tables', ... )` so WooCommerce recognizes the plugin as HPOS-compatible instead of listing it as untested.
* Fixed: uninstall cleanup now removes the plugin's meta flags from the correct table (HPOS or `wp_postmeta`) and also removes the `_hns_hold_target` flag, which the previous uninstall routine missed entirely.
* Removed: a `Plugin URI` header pointing at a WordPress.org plugin listing this plugin was never actually submitted to.

= 1.2.0 =
* Added Russian and English translations with a `gettext`-based Russian fallback for installs missing the compiled `.mo` file.

== Upgrade Notice ==

= 1.5.1 =
Confirms compatibility with WooCommerce 11.3 and shows a confirmation after saving the settings.

= 1.5.0 =
Adds the option to upgrade to the Professional version. The free plugin works exactly as before.

= 1.4.6 =
The Russian translation is no longer bundled with the plugin; translations now come from translate.wordpress.org. No functional changes.

= 1.4.4 =
A subscription waiting for a renewal payment can no longer be activated by a status change of its original order.

= 1.4.3 =
Subscriptions paid by bank transfer or another late-confirmed method are now held until the order status you chose. Recommended for stores that accept such payments.

= 1.4.0 =
Internal rename only; your settings are carried over automatically. If you have custom code hooked to this plugin's `hns_` hooks, switch it to the `hnsub_` names.

= 1.2.1 =
Fixes a WooCommerce HPOS compatibility bug in the duplicate-activation guard and in uninstall cleanup. Update recommended for any store using HPOS.
