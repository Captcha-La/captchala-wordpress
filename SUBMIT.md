# CaptchaLa — wordpress.org submission SOP

This file documents the manual one-time and ongoing flow for shipping the
CaptchaLa WordPress plugin to https://wordpress.org/plugins/. Automation is
out of scope per the spec — CI produces an upload-ready ZIP, the human
operator submits.

## 1. Prerequisites (one-time)

1. **wordpress.org account.** Register at https://login.wordpress.org/register
   under an email tied to the CaptchaLa team (`supply@captcha.la`).
2. **Two-factor auth enabled.** wordpress.org increasingly requires 2FA on
   plugin author accounts.
3. **Plugin developer agreement read.** https://developer.wordpress.org/plugins/
4. **Make sure the slug `captchala` is free.** Reserve it via the new-plugin
   submission step below — wordpress.org auto-assigns the slug from the
   `=== ... ===` heading in `readme.txt`.

## 2. Initial submission

1. **Validate `readme.txt`.** Drop the file into
   https://wordpress.org/plugins/developers/readme-validator/ and resolve every
   warning. The validator is strict about the `Stable tag` line and the
   `Tags:` line (max 5 tags, all lowercase, hyphen-separated multi-words).
2. **Asset audit.** Confirm the `assets/` directory contains:
    * `icon-256x256.png` and `icon-128x128.png` — square plugin icons.
    * `banner-1544x500.png` — main banner. Optional `-rtl` variant.
    * `banner-772x250.png` — small / Retina banner.
    * `screenshot-1.png` … `screenshot-5.png` — max 1200×900 each.
   Any non-PNG asset gets rejected. The bundled placeholders are solid-colour
   PNGs intended only for development; **swap them for final artwork before
   submitting.**
3. **Build the ZIP.** Run `./build.sh` — it produces
   `release/wordpress-1.0.0.zip` containing the captchala/ directory with
   `vendor/` already installed. wordpress.org rejects ZIPs that contain a
   wrapping versioned folder (e.g. `captchala-1.0.0/`); the build script
   normalises that for you.
4. **New plugin submission form:**
   https://wordpress.org/plugins/developers/add/
   * Upload the ZIP, paste a 50-200 char "What does this plugin do?" blurb.
   * Reference the GitHub mirror (`https://github.com/Captcha-La/captchala-wordpress`)
     in the "Plugin URL" field — the review team often clicks through.
5. **Wait for the review.** Typical SLA is **1-3 weeks** for a first-time
   plugin. They almost always come back once with at least one nit — common
   rejection reasons:
    * Missing `prefix_` on free-floating helper functions
    * Loading external JS that isn't enqueued
    * `eval()` / `base64_decode()` flagged as obfuscation
    * Settings page missing nonce or capability checks
    * `readme.txt` mentioning a competitor by trademarked name without
      disclosure (we mention your existing CAPTCHA as the
      products we replace — that's allowed, but keep it factual)

## 3. Post-approval (one-time)

Once approved, wordpress.org provisions an SVN repo at
`https://plugins.svn.wordpress.org/captchala/`. From there ongoing releases
are an SVN flow, not a re-upload:

```
svn co https://plugins.svn.wordpress.org/captchala captchala-svn
cd captchala-svn
# Copy the new release into trunk/
rsync -a --delete --exclude '.git' --exclude 'release/' \
    /path/to/dash/plugins/wordpress/ trunk/
svn add --force trunk
svn commit -m "Release 1.0.0"
# Tag the release
svn cp trunk tags/1.0.0
svn commit -m "Tag 1.0.0"
```

Update `Stable tag:` in `readme.txt` to the new version on every release —
that's the line wordpress.org actually serves to update-checkers.

## 4. Asset uploads

Banners / icons / screenshots **are not committed to trunk**. They live in a
sibling SVN directory:

```
svn co https://plugins.svn.wordpress.org/captchala/assets captchala-assets
cp banner-1544x500.png banner-772x250.png icon-{128,256}*.png screenshot-*.png \
   captchala-assets/
svn add --force captchala-assets/*.png
svn commit -m "Update marketplace assets"
```

## 5. Privacy / data-processing disclosures

CaptchaLa transmits the visitor's IP and user-agent to CaptchaLa at
`apiv1.captcha.la`. The canonical privacy disclosure lives at
`sdk/DATA_COLLECTION_DISCLOSURE.md` in the CaptchaLa monorepo and is linked
from the plugin's settings page. Any change in the data flow requires updating
that document **before** publishing the next plugin release, otherwise the
wordpress.org review team will (correctly) flag the divergence.

## 6. Update cadence

We bump the canonical version in `plugins/VERSION` and run
`plugins/shared/scripts/sync-version.sh` to propagate to every manifest.
A `wordpress/v<semver>` git tag triggers the GitHub Actions matrix that
produces a new ZIP under the GitHub Release. The ZIP is then uploaded via
SVN per §3.

## 7. Common rejection cookbook

| Reviewer note | Fix |
|---|---|
| "Use prefix on all functions" | We're 100% PSR-4 — point them to `src/Plugin.php`. |
| "Don't load external scripts" | The CDN loader is whitelisted on captcha.la — argue from the WP plugin guidelines section permitting external CAPTCHA APIs. |
| "Missing escaping" | We use `wp_kses`, `esc_attr`, `esc_html`. The widget HTML comes from `Captchala\Cms\Widget` which already runs `htmlspecialchars` on attribute values. |
| "Missing nonces" | All admin saves go through the WP Settings API; AJAX endpoints check `check_ajax_referer( Plugin::NONCE_ACTION, 'nonce' )`. |
| "Unsanitised `$_POST`" | Every read is wrapped in `sanitize_text_field( wp_unslash( ... ) )`. |
