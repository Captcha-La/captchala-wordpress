# CaptchaLa for WordPress — integration matrix

This file lists every WordPress / WooCommerce / form-plugin integration the
CaptchaLa plugin ships with, plus the v1.1 backlog. The shape is the
single source of truth for the marketplace listing's "Compatible with…"
section.

## v1.0 (shipping now)

### WordPress core

| Form | Action | Render hook | Validate hook |
|---|---|---|---|
| Login (wp-login.php) | `login` | `login_form` | `wp_authenticate_user` |
| Registration | `register` | `register_form` | `registration_errors` |
| Lost password | `lost_password` | `lostpassword_form` | `lostpassword_post` |
| Comment form | `comment` | `comment_form_after_fields` + `comment_form_logged_in_after` | `preprocess_comment` |

### WooCommerce (auto-enabled when WC is active)

| Form | Action | Render hook | Validate hook |
|---|---|---|---|
| My-account login | `login` | `woocommerce_login_form` | `authenticate` |
| My-account register | `register` | `woocommerce_register_form` | `woocommerce_register_post` |
| My-account lost password | `lost_password` | `woocommerce_lostpassword_form` | `lostpassword_post` |
| Checkout (classic) | `checkout` | `woocommerce_review_order_before_submit` | `woocommerce_checkout_process` |
| Checkout (block) | `checkout` | `render_block_woocommerce/checkout-actions-block` filter | `woocommerce_store_api_checkout_update_order_from_request` |
| Pay for order | `pay_for_order` | `woocommerce_pay_order_before_submit` | `woocommerce_after_pay_action` |
| Account creation during checkout | `account_create` | `woocommerce_after_checkout_registration_form` | `woocommerce_register_post` (in checkout context) |

### Third-party form plugins (auto-detected via `class_exists()`)

| Plugin | Action | Class detected | Source file |
|---|---|---|---|
| Contact Form 7 | `contact_form` | `WPCF7` | `src/Integrations/Cf7.php` |
| WPForms (Lite + Pro) | `contact_form` | `WPForms`, `wpforms()` | `src/Integrations/Wpforms.php` |
| Gravity Forms | `contact_form` | `GFForms` | `src/Integrations/Gravity.php` |
| Forminator | `contact_form` | `Forminator_API` | `src/Integrations/Forminator.php` |
| Formidable Forms | `contact_form` | `FrmAppController` | `src/Integrations/Formidable.php` |
| Fluent Forms | `contact_form` | `\FluentForm\App\App`, `FLUENTFORM` | `src/Integrations/Fluent.php` |
| Elementor Pro Forms | `contact_form` | `ELEMENTOR_PRO_VERSION`, `\ElementorPro\Plugin` | `src/Integrations/Elementor.php` |
| Divi Builder forms | `contact_form` | `ET_Builder_Plugin` | `src/Integrations/Divi.php` |
| BuddyPress (signup) | `register` | `BuddyPress` | `src/Integrations/BuddyPress.php` |
| bbPress (topic + reply) | `forum_post` | `bbPress` | `src/Integrations/Bbpress.php` |
| Ultimate Member (login + register) | `register` | `UM` | `src/Integrations/UltimateMember.php` |
| MemberPress (login + signup) | `register` | `MeprBaseCtrl` | `src/Integrations/MemberPress.php` |
| Easy Digital Downloads checkout | `checkout` | `Easy_Digital_Downloads` | `src/Integrations/Edd.php` |
| MailPoet subscribe | `contact_form` | `\MailPoet\API\API` | `src/Integrations/MailPoet.php` |

## v1.1 backlog (deferred)

These plugins have either smaller installed bases or unusually idiosyncratic
hooks; we ship them in the next release rather than padding 1.0.

* **Themes / page builders:** Avada / Fusion Builder, Beaver Builder, Brizy,
  Kadence Blocks, Spectra (Ultimate Addons), Otter Blocks, Blocksy theme.
* **Form plugins:** Ninja Forms (NF), Quform, SureForms, MetForm, Everest Forms.
* **Membership / e-learning:** Paid Memberships Pro, Tutor LMS, LearnDash,
  LearnPress, ProfileBuilder, SimpleMembership.
* **Newsletters:** Mailchimp For WP, Sendinblue / Brevo, Icegram Express.
* **Forums / community:** WPDiscuz, wpForo, Asgaros Forum.
* **Auth:** Theme My Login, LoginSignupPopup, PasswordProtected,
  Wordfence-aware compatibility shims.
* **Misc surface area:** Jetpack Contact Form, Protect Site Content gate,
  WP Job Openings, Customer Reviews for WooCommerce.

The full list is tracked at the top of `src/Plugin.php` so it doesn't get
lost in this file.

## How to add a new integration

1. Create `src/Integrations/<Name>.php` extending `AbstractIntegration`.
2. Implement `is_available()` (host-detection — always `class_exists()` /
   `function_exists()` / `defined()` checks, never `is_plugin_active()`
   so it works at `plugins_loaded`).
3. Implement `option_key()` returning the settings field name.
4. Implement `action()` returning a `Captchala\Cms\Action::*` constant.
5. Wire the host plugin's render + validate hooks in `init()`.
6. Add the class to the `$third_party` array in `Plugin::register_integrations()`.
7. Add a default toggle (`'mykey' => 1`) to `Plugin::default_settings()`.
8. Add an auto-detect entry to `SettingsPage::detected_third_parties()`.
9. Add the row to this file's matrix.
