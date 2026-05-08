=== CaptchaLa ===
Contributors: captchala
Tags: captcha, captchala, antispam, bot-protection, anti-spam
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Smart privacy-first CAPTCHA for WordPress, WooCommerce, and 14+ form plugins. Mandatory server-token anti-replay.

== Description ==

**Homepage:** [https://captcha.la/](https://captcha.la/) · **Dashboard:** [https://dash.captcha.la/](https://dash.captcha.la/) · **Pricing:** [https://captcha.la/pricing](https://captcha.la/pricing)

**CaptchaLa** is a privacy-first CAPTCHA and anti-spam service. This plugin is the official WordPress integration — it ships built-in protection for WordPress core forms, WooCommerce, and the most popular third-party form plugins, with one mandatory mode: server-issued tokens that can't be replayed.

= Why CaptchaLa? =

* **Privacy-first.** No personal-data harvesting, no behavioural ad signals, no tracking.
* **Stronger token contract.** Every page render issues a server-side `sct_` token bound to action + IP; the browser only ever ships back a single-use `pt_` token. Replays, action mismatches, and IP changes are rejected at the SDK boundary.
* **Free tier** for small sites. Paid plans for high-volume traffic and the optional content-moderation add-on.
* **No hand-coded image CAPTCHAs.** The challenge is solved entirely by the CaptchaLa service — this plugin is thin glue.
* **Auto-detected form integrations.** Drop the plugin in and the settings page lights up with the form plugins it found on your site.

= Built-in protection points =

* WordPress core: login, registration, lost-password, comments
* WooCommerce: classic + block checkout, pay-for-order, account creation, my-account login / register / lost-password
* Third-party form plugins (auto-detected): Contact Form 7, WPForms, Gravity Forms, Forminator, Formidable Forms, Fluent Forms, Elementor Pro Forms, Divi Builder forms, BuddyPress, bbPress, Ultimate Member, MemberPress, Easy Digital Downloads, MailPoet

More integrations land in v1.1 — see the bundled `INTEGRATIONS.md` for the full target list.

= AI / Abilities API =

CaptchaLa registers a small set of read-only abilities under the WordPress Abilities API (WP 6.5+) so AI agents and automation tools can introspect the plugin's configuration without scraping admin pages.

== Installation ==

1. Upload the `captchala` directory to `/wp-content/plugins/`, or install via the WordPress plugin browser.
2. Activate the plugin.
3. Sign up at [https://dash.captcha.la/](https://dash.captcha.la/) and copy your **App Key** and **App Secret**.
4. Open **Settings → CaptchaLa**, paste the keys, click **Test connection**, and save.
5. Toggle the integrations you want protected.

== Frequently Asked Questions ==

= Is CaptchaLa free? =

A free tier covers small sites. High-volume traffic and content-moderation features require a paid plan — see [https://captcha.la/pricing](https://captcha.la/pricing).

= Do I need to install the SDK separately? =

No. The PHP SDK is bundled in the plugin's `vendor/` directory by the build pipeline, so you don't need Composer.

= Does it work with my form plugin? =

CaptchaLa ships first-class integrations for the 14 most-used form plugins on wordpress.org. See `INTEGRATIONS.md` in the plugin folder for the full list and the v1.1 roadmap.

= Does it work with WooCommerce Block Checkout? =

Yes. The Checkout integration covers both the classic shortcode-based checkout and the new block-based checkout.

= Will it block logged-in admins? =

No. By default the plugin skips CAPTCHA for users with `manage_options` or `edit_others_posts`. The toggle is on the settings page.

= How does the server_token flow work? =

On every page render the plugin issues a one-shot `sct_` token from your CaptchaLa account, scoped to the action (login / register / checkout / …) and bound to the visitor's IP. The browser SDK exchanges that token for a `pt_` token after the human challenge passes. The plugin validates the `pt_` token server-side on form submit. Replays, action-mismatches, and IP changes are all rejected.

== Screenshots ==

1. Settings page — credentials section with "Test connection" button.
2. Settings page — integration toggles auto-detected from your install.
3. WordPress login form with the CaptchaLa widget rendered.
4. WooCommerce checkout with the CaptchaLa widget above the Place Order button.

== Changelog ==

= 1.0.0 =
* Initial release.
* WordPress core: login, register, lost-password, comment.
* WooCommerce: checkout (classic + block), pay-for-order, account creation, login, register, lost-password.
* Third-party: Contact Form 7, WPForms, Gravity Forms, Forminator, Formidable, Fluent Forms, Elementor Pro Forms, Divi, BuddyPress, bbPress, Ultimate Member, MemberPress, Easy Digital Downloads, MailPoet.
* WP 6.5+ Abilities API: read-only `captchala/get-config` and `captchala/get-stats`.

== Upgrade Notice ==

= 1.0.0 =
First public release.
