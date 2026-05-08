<?php
/**
 * Settings → CaptchaLa admin page.
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Settings;

use Captchala\Wp\Plugin;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Renders the WP Settings API form under Settings → CaptchaLa.
 *
 * Field layout:
 *   1. Credentials section: App Key, App Secret
 *   2. Widget section: product (popup/float/embed/bind), lang
 *   3. Behaviour section: skip_for_admins
 *   4. WordPress core toggles
 *   5. WooCommerce toggles (only emitted when Woo is active)
 *   6. Third-party form-plugin toggles (only emitted when host plugin is detected)
 *
 * AJAX "Test connection" button hits Plugin::ajax_test_connection().
 */
final class SettingsPage {

	public const PAGE_SLUG    = 'captchala';
	public const SECTION_KEYS = 'captchala_section_keys';
	public const SECTION_UX   = 'captchala_section_ux';
	public const SECTION_WP   = 'captchala_section_wp';
	public const SECTION_WC   = 'captchala_section_wc';
	public const SECTION_3P   = 'captchala_section_3p';

	private Plugin $plugin;

	public function __construct( Plugin $plugin ) {
		$this->plugin = $plugin;
	}

	public function init(): void {
		add_action( 'admin_menu',  [ $this, 'register_menu' ] );
		add_action( 'admin_init',  [ $this, 'register_settings' ] );
		add_action( 'admin_enqueue_scripts', [ $this, 'enqueue_assets' ] );
	}

	public function register_menu(): void {
		add_options_page(
			__( 'CaptchaLa', 'captchala' ),
			__( 'CaptchaLa', 'captchala' ),
			'manage_options',
			self::PAGE_SLUG,
			[ $this, 'render_page' ]
		);
	}

	public function enqueue_assets( string $hook ): void {
		if ( 'settings_page_' . self::PAGE_SLUG !== $hook ) {
			return;
		}
		$base = CAPTCHALA_WP_URL;
		wp_enqueue_style(
			'captchala-admin',
			$base . 'assets/css/admin.css',
			[],
			CAPTCHALA_WP_VERSION
		);
		wp_enqueue_script(
			'captchala-admin',
			$base . 'assets/js/admin.js',
			[ 'jquery' ],
			CAPTCHALA_WP_VERSION,
			true
		);
		wp_localize_script(
			'captchala-admin',
			'CaptchalaAdmin',
			[
				'ajaxUrl'        => admin_url( 'admin-ajax.php' ),
				'nonce'          => wp_create_nonce( Plugin::NONCE_ACTION ),
				'testAction'     => Plugin::AJAX_TEST_CONNECTION,
				'i18n'           => [
					'testing'        => __( 'Testing…', 'captchala' ),
					'testButton'     => __( 'Test connection', 'captchala' ),
					'connOk'         => __( 'Connection OK.', 'captchala' ),
					'connFail'       => __( 'Connection failed.', 'captchala' ),
				],
			]
		);
	}

	public function register_settings(): void {
		register_setting(
			'captchala_settings_group',
			Plugin::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize' ],
				'default'           => $this->plugin->default_settings(),
			]
		);

		// --- Credentials ---
		add_settings_section(
			self::SECTION_KEYS,
			__( 'API credentials', 'captchala' ),
			function (): void {
				printf(
					'<p>%s</p>',
					wp_kses(
						sprintf(
							/* translators: %s: dashboard URL */
							__( 'Find your App Key and App Secret in the <a href="%s" target="_blank" rel="noopener">CaptchaLa dashboard</a>. New customers can sign up for free.', 'captchala' ),
							'https://dash.captcha.la/'
						),
						[ 'a' => [ 'href' => true, 'target' => true, 'rel' => true ] ]
					)
				);
			},
			self::PAGE_SLUG
		);

		$this->field( 'app_key',    __( 'App Key', 'captchala' ),    'render_text',     self::SECTION_KEYS );
		$this->field( 'app_secret', __( 'App Secret', 'captchala' ), 'render_password', self::SECTION_KEYS );
		add_settings_field(
			'captchala_test_button',
			'',
			[ $this, 'render_test_button' ],
			self::PAGE_SLUG,
			self::SECTION_KEYS
		);

		// --- Widget UX ---
		add_settings_section(
			self::SECTION_UX,
			__( 'Widget appearance', 'captchala' ),
			'__return_false',
			self::PAGE_SLUG
		);
		$this->field( 'product',          __( 'Widget mode', 'captchala' ),    'render_product',          self::SECTION_UX );
		$this->field( 'lang',             __( 'Language', 'captchala' ),       'render_lang',             self::SECTION_UX );
		$this->field( 'theme',            __( 'Theme', 'captchala' ),          'render_theme',            self::SECTION_UX );
		$this->field( 'theme_color',      __( 'Custom: main color', 'captchala' ),         'render_theme_color',      self::SECTION_UX );
		$this->field( 'theme_gradient',   __( 'Custom: background gradient', 'captchala' ), 'render_text_or_blank',    self::SECTION_UX );
		$this->field( 'theme_hover',      __( 'Custom: hover gradient', 'captchala' ),      'render_text_or_blank',    self::SECTION_UX );
		$this->field( 'theme_brightness', __( 'Custom: brightness', 'captchala' ),          'render_theme_brightness', self::SECTION_UX );
		$this->field( 'theme_radius',     __( 'Custom: corner radius', 'captchala' ),       'render_text_or_blank',    self::SECTION_UX );
		$this->field( 'skip_for_admins', __( 'Skip CAPTCHA for logged-in admins / editors', 'captchala' ), 'render_checkbox', self::SECTION_UX );

		// --- WordPress core toggles ---
		add_settings_section(
			self::SECTION_WP,
			__( 'WordPress core', 'captchala' ),
			'__return_false',
			self::PAGE_SLUG
		);
		$this->field( 'wp_login',         __( 'Login form', 'captchala' ),         'render_checkbox', self::SECTION_WP );
		$this->field( 'wp_register',      __( 'Registration form', 'captchala' ),  'render_checkbox', self::SECTION_WP );
		$this->field( 'wp_lost_password', __( 'Lost-password form', 'captchala' ), 'render_checkbox', self::SECTION_WP );
		$this->field( 'wp_comment',       __( 'Comment form', 'captchala' ),       'render_checkbox', self::SECTION_WP );

		// --- WooCommerce toggles ---
		if ( $this->plugin->is_woocommerce_active() ) {
			add_settings_section(
				self::SECTION_WC,
				__( 'WooCommerce', 'captchala' ),
				'__return_false',
				self::PAGE_SLUG
			);
			$this->field( 'wc_login',          __( 'My-account login', 'captchala' ),       'render_checkbox', self::SECTION_WC );
			$this->field( 'wc_register',       __( 'My-account register', 'captchala' ),    'render_checkbox', self::SECTION_WC );
			$this->field( 'wc_lost_password',  __( 'My-account lost password', 'captchala' ), 'render_checkbox', self::SECTION_WC );
			$this->field( 'wc_account_create', __( 'Checkout: account creation', 'captchala' ), 'render_checkbox', self::SECTION_WC );
			$this->field( 'wc_checkout',       __( 'Checkout', 'captchala' ),               'render_checkbox', self::SECTION_WC );
			$this->field( 'wc_pay_for_order',  __( 'Pay for order', 'captchala' ),          'render_checkbox', self::SECTION_WC );
		}

		// --- Third-party integrations (only render rows when host plugin is detected) ---
		$third_party = $this->detected_third_parties();
		if ( ! empty( $third_party ) ) {
			add_settings_section(
				self::SECTION_3P,
				__( 'Detected form plugins', 'captchala' ),
				function (): void {
					printf(
						'<p class="description">%s</p>',
						esc_html__( 'CaptchaLa auto-detected the following form plugins on this site. Toggle individual integrations as needed.', 'captchala' )
					);
				},
				self::PAGE_SLUG
			);
			foreach ( $third_party as $key => $label ) {
				$this->field( $key, $label, 'render_checkbox', self::SECTION_3P );
			}
		}
	}

	/**
	 * @return array<string,string>
	 */
	private function detected_third_parties(): array {
		$candidates = [
			'cf7'            => [ 'WPCF7',                 __( 'Contact Form 7', 'captchala' ) ],
			'wpforms'        => [ 'WPForms',               __( 'WPForms', 'captchala' ) ],
			'gravity'        => [ 'GFForms',               __( 'Gravity Forms', 'captchala' ) ],
			'forminator'     => [ 'Forminator_API',        __( 'Forminator', 'captchala' ) ],
			'formidable'     => [ 'FrmAppController',      __( 'Formidable Forms', 'captchala' ) ],
			'fluent'         => [ 'FluentForm\\App\\App',  __( 'Fluent Forms', 'captchala' ) ],
			'elementor'      => [ '\\ElementorPro\\Plugin', __( 'Elementor Pro Forms', 'captchala' ) ],
			'divi'           => [ 'ET_Builder_Plugin',     __( 'Divi / Bloom', 'captchala' ) ],
			'buddypress'     => [ 'BuddyPress',            __( 'BuddyPress', 'captchala' ) ],
			'bbpress'        => [ 'bbPress',               __( 'bbPress', 'captchala' ) ],
			'ultimatemember' => [ 'UM',                    __( 'Ultimate Member', 'captchala' ) ],
			'memberpress'    => [ 'MeprBaseCtrl',          __( 'MemberPress', 'captchala' ) ],
			'edd'            => [ 'Easy_Digital_Downloads', __( 'Easy Digital Downloads', 'captchala' ) ],
			'mailpoet'       => [ 'MailPoet\\API\\API',    __( 'MailPoet', 'captchala' ) ],
		];
		$out = [];
		foreach ( $candidates as $key => [ $cls, $label ] ) {
			if ( class_exists( $cls ) || function_exists( $cls ) ) {
				$out[ $key ] = $label;
			}
		}
		return $out;
	}

	private function field( string $key, string $label, string $renderer, string $section ): void {
		add_settings_field(
			'captchala_' . $key,
			$label,
			[ $this, $renderer ],
			self::PAGE_SLUG,
			$section,
			[ 'key' => $key, 'label_for' => 'captchala_' . $key ]
		);
	}

	// -------------------------------------------------------------------------
	// Field renderers
	// -------------------------------------------------------------------------

	public function render_text( array $args ): void {
		$key = (string) $args['key'];
		$val = (string) $this->plugin->get_setting( $key, '' );
		printf(
			'<input type="text" id="captchala_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" autocomplete="off" />',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY ),
			esc_attr( $val )
		);
	}

	public function render_password( array $args ): void {
		$key = (string) $args['key'];
		$val = (string) $this->plugin->get_setting( $key, '' );
		printf(
			'<input type="password" id="captchala_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" autocomplete="new-password" />',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY ),
			esc_attr( $val )
		);
	}

	public function render_checkbox( array $args ): void {
		$key = (string) $args['key'];
		$val = $this->plugin->is_enabled( $key );
		printf(
			'<label><input type="checkbox" id="captchala_%1$s" name="%2$s[%1$s]" value="1"%3$s /> %4$s</label>',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY ),
			$val ? ' checked' : '',
			esc_html__( 'Enabled', 'captchala' )
		);
	}

	public function render_product( array $args ): void {
		$key     = (string) $args['key'];
		$current = (string) $this->plugin->get_setting( $key, 'bind' );
		$opts    = [
			'bind'  => __( 'Bind — invisible; intercepts submit click and runs challenge (1-click UX, recommended for native forms)', 'captchala' ),
			'popup' => __( 'Popup — visible trigger bar above the submit button; click → fullscreen modal challenge → user clicks submit', 'captchala' ),
			'float' => __( 'Float — visible trigger bar above the submit button; click → inline floating panel → user clicks submit (recommended for Block Checkout)', 'captchala' ),
			'embed' => __( 'Embed — inline checkbox + challenge directly in the form; user solves, then clicks submit', 'captchala' ),
		];
		printf(
			'<select id="captchala_%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY )
		);
		foreach ( $opts as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	/**
	 * BCP-47 → display label. Order: 'auto' first, then alphabetical by tag.
	 * Mirrors docs-site/supported-languages.md (54 locales).
	 *
	 * @return array<string,string>
	 */
	public static function lang_options(): array {
		return [
			'auto'  => __( 'Auto (follow visitor browser)', 'captchala' ),
			'ar'    => 'العربية — Arabic',
			'bg'    => 'Български — Bulgarian',
			'bn'    => 'বাংলা — Bengali',
			'cs'    => 'Čeština — Czech',
			'da'    => 'Dansk — Danish',
			'de'    => 'Deutsch — German',
			'el'    => 'Ελληνικά — Greek',
			'en'    => 'English',
			'es'    => 'Español — Spanish',
			'et'    => 'Eesti — Estonian',
			'fa'    => 'فارسی — Persian',
			'fi'    => 'Suomi — Finnish',
			'fil'   => 'Filipino',
			'fr'    => 'Français — French',
			'gu'    => 'ગુજરાતી — Gujarati',
			'he'    => 'עברית — Hebrew',
			'hi'    => 'हिन्दी — Hindi',
			'hr'    => 'Hrvatski — Croatian',
			'hu'    => 'Magyar — Hungarian',
			'id'    => 'Bahasa Indonesia',
			'it'    => 'Italiano — Italian',
			'ja'    => '日本語 — Japanese',
			'km'    => 'ខ្មែរ — Khmer',
			'kn'    => 'ಕನ್ನಡ — Kannada',
			'ko'    => '한국어 — Korean',
			'lo'    => 'ລາວ — Lao',
			'ml'    => 'മലയാളം — Malayalam',
			'mr'    => 'मराठी — Marathi',
			'ms'    => 'Bahasa Melayu — Malay',
			'my'    => 'မြန်မာ — Burmese',
			'ne'    => 'नेपाली — Nepali',
			'nl'    => 'Nederlands — Dutch',
			'no'    => 'Norsk — Norwegian',
			'pa'    => 'ਪੰਜਾਬੀ — Punjabi',
			'pl'    => 'Polski — Polish',
			'pt'    => 'Português — Portuguese',
			'pt-BR' => 'Português (Brasil)',
			'ro'    => 'Română — Romanian',
			'ru'    => 'Русский — Russian',
			'si'    => 'සිංහල — Sinhala',
			'sk'    => 'Slovenčina — Slovak',
			'sl'    => 'Slovenščina — Slovenian',
			'sr'    => 'Српски — Serbian',
			'sv'    => 'Svenska — Swedish',
			'sw'    => 'Kiswahili — Swahili',
			'ta'    => 'தமிழ் — Tamil',
			'te'    => 'తెలుగు — Telugu',
			'th'    => 'ไทย — Thai',
			'tr'    => 'Türkçe — Turkish',
			'uk'    => 'Українська — Ukrainian',
			'ur'    => 'اردو — Urdu',
			'vi'    => 'Tiếng Việt — Vietnamese',
			'zh-CN' => '简体中文 — Chinese (Simplified)',
			'zh-TW' => '繁體中文 — Chinese (Traditional)',
		];
	}

	/**
	 * Theme presets exposed in the dropdown. Curated for SaaS-restrained,
	 * brand-safe palettes (no pure purple / no neon). The `*_curated` ones
	 * resolve to a custom theme block at render time; the SDK presets
	 * (`default`, `dark`, `stereoscopic`) pass through as-is.
	 *
	 * @return array<string,string>
	 */
	public static function theme_options(): array {
		return [
			'default'      => __( 'Default — brand blue', 'captchala' ),
			'dark'         => __( 'Dark', 'captchala' ),
			'stereoscopic' => __( 'Stereoscopic (3D)', 'captchala' ),
			'slate'        => __( 'Slate — neutral gray-blue', 'captchala' ),
			'emerald'      => __( 'Emerald — green', 'captchala' ),
			'amber'        => __( 'Amber — warm', 'captchala' ),
			'rose'         => __( 'Rose — soft red', 'captchala' ),
			'custom'       => __( 'Custom — pick my own colors', 'captchala' ),
		];
	}

	/**
	 * Preset → main color hex. Used by Widget at render time when the user
	 * picks one of the curated presets above. Keeps in sync with
	 * sdk/php/src/Cms/Widget.php::CURATED_PRESETS.
	 *
	 * @return array<string,string>
	 */
	public static function curated_preset_color(): array {
		return [
			'slate'   => '#475569',
			'emerald' => '#10B981',
			'amber'   => '#F59E0B',
			'rose'    => '#F43F5E',
		];
	}

	public function render_lang( array $args ): void {
		$key     = (string) $args['key'];
		$current = (string) $this->plugin->get_setting( $key, 'auto' );
		printf(
			'<select id="captchala_%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY )
		);
		foreach ( self::lang_options() as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( '54 locales supported. "Auto" follows the visitor\'s browser language.', 'captchala' ) . '</p>';
	}

	public function render_theme( array $args ): void {
		$key     = (string) $args['key'];
		$current = (string) $this->plugin->get_setting( $key, 'default' );
		printf(
			'<select id="captchala_%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY )
		);
		foreach ( self::theme_options() as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
	}

	public function render_theme_color( array $args ): void {
		$key     = (string) $args['key'];
		$current = (string) $this->plugin->get_setting( $key, '' );
		printf(
			'<input type="color" id="captchala_%1$s" name="%2$s[%1$s]" value="%3$s" />',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY ),
			esc_attr( $current !== '' ? $current : '#3B82F6' )
		);
		printf(
			' <input type="text" name="%1$s[%2$s_text]" value="%3$s" placeholder="#3B82F6" pattern="^#[0-9A-Fa-f]{6}$" style="width:9em" aria-label="%4$s" />',
			esc_attr( Plugin::OPTION_KEY ),
			esc_attr( $key ),
			esc_attr( $current ),
			esc_attr__( 'Hex colour code', 'captchala' )
		);
		echo '<p class="description">' . esc_html__( 'Only used when Theme = "Custom". Hex code, e.g. #3B82F6.', 'captchala' ) . '</p>';
	}

	public function render_text_or_blank( array $args ): void {
		$key         = (string) $args['key'];
		$current     = (string) $this->plugin->get_setting( $key, '' );
		$placeholder = '';
		if ( $key === 'theme_gradient' || $key === 'theme_hover' ) {
			$placeholder = 'linear-gradient(135deg, #3B82F6 0%, #1D4ED8 100%)';
		} elseif ( $key === 'theme_radius' ) {
			$placeholder = '8px';
		}
		printf(
			'<input type="text" id="captchala_%1$s" name="%2$s[%1$s]" value="%3$s" class="regular-text" placeholder="%4$s" />',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY ),
			esc_attr( $current ),
			esc_attr( $placeholder )
		);
		echo '<p class="description">' . esc_html__( 'Only used when Theme = "Custom". Leave blank for SDK default.', 'captchala' ) . '</p>';
	}

	public function render_theme_brightness( array $args ): void {
		$key     = (string) $args['key'];
		$current = (string) $this->plugin->get_setting( $key, 'system' );
		$opts    = [
			'system' => __( 'System (follow OS)', 'captchala' ),
			'light'  => __( 'Light', 'captchala' ),
			'dark'   => __( 'Dark', 'captchala' ),
		];
		printf(
			'<select id="captchala_%1$s" name="%2$s[%1$s]">',
			esc_attr( $key ),
			esc_attr( Plugin::OPTION_KEY )
		);
		foreach ( $opts as $value => $label ) {
			printf(
				'<option value="%1$s"%2$s>%3$s</option>',
				esc_attr( $value ),
				selected( $current, $value, false ),
				esc_html( $label )
			);
		}
		echo '</select>';
		echo '<p class="description">' . esc_html__( 'Only used when Theme = "Custom".', 'captchala' ) . '</p>';
	}

	public function render_test_button(): void {
		printf(
			'<button type="button" class="button" id="captchala-test-connection">%s</button> <span id="captchala-test-result" aria-live="polite"></span>',
			esc_html__( 'Test connection', 'captchala' )
		);
	}

	// -------------------------------------------------------------------------
	// Sanitization
	// -------------------------------------------------------------------------

	/**
	 * @param mixed $input
	 * @return array<string,mixed>
	 */
	public function sanitize( $input ): array {
		$defaults = $this->plugin->default_settings();
		$out      = $defaults;

		if ( ! is_array( $input ) ) {
			return $out;
		}

		// Free-text strings
		foreach ( [ 'app_key', 'app_secret' ] as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$out[ $k ] = sanitize_text_field( (string) $input[ $k ] );
			}
		}

		// Whitelisted product
		$product = isset( $input['product'] ) ? sanitize_text_field( (string) $input['product'] ) : 'bind';
		$out['product'] = in_array( $product, [ 'bind', 'embed', 'float', 'popup' ], true ) ? $product : 'bind';

		// Whitelisted lang (BCP-47 from the supported set)
		$lang = isset( $input['lang'] ) ? sanitize_text_field( (string) $input['lang'] ) : 'auto';
		$out['lang'] = array_key_exists( $lang, self::lang_options() ) ? $lang : 'auto';

		// Whitelisted theme preset
		$theme = isset( $input['theme'] ) ? sanitize_text_field( (string) $input['theme'] ) : 'default';
		$out['theme'] = array_key_exists( $theme, self::theme_options() ) ? $theme : 'default';

		// Theme color — accept #RRGGBB only. Color picker submits hex; the
		// optional text field is just a mirror so users can copy-paste.
		$theme_color = isset( $input['theme_color'] ) ? trim( (string) $input['theme_color'] ) : '';
		if ( $theme_color === '' && isset( $input['theme_color_text'] ) ) {
			$theme_color = trim( (string) $input['theme_color_text'] );
		}
		$out['theme_color'] = preg_match( '/^#[0-9A-Fa-f]{6}$/', $theme_color ) ? strtolower( $theme_color ) : '';

		// Free-form CSS strings — kept short, lightly sanitized via wp_strip_all_tags
		// so a stray <script> can't sneak through into the rendered widget config.
		foreach ( [ 'theme_gradient', 'theme_hover', 'theme_radius' ] as $k ) {
			if ( isset( $input[ $k ] ) ) {
				$v = trim( (string) $input[ $k ] );
				$out[ $k ] = $v === '' ? '' : sanitize_text_field( wp_strip_all_tags( $v ) );
			} else {
				$out[ $k ] = '';
			}
		}

		// Whitelisted brightness
		$brightness = isset( $input['theme_brightness'] ) ? sanitize_text_field( (string) $input['theme_brightness'] ) : 'system';
		$out['theme_brightness'] = in_array( $brightness, [ 'system', 'light', 'dark' ], true ) ? $brightness : 'system';

		// Booleans (every default checkbox)
		$reserved = [ 'app_key', 'app_secret', 'product', 'lang', 'theme', 'theme_color', 'theme_gradient', 'theme_hover', 'theme_radius', 'theme_brightness' ];
		foreach ( $defaults as $k => $v ) {
			if ( in_array( $k, $reserved, true ) ) {
				continue;
			}
			$out[ $k ] = ! empty( $input[ $k ] ) ? 1 : 0;
		}

		return $out;
	}

	// -------------------------------------------------------------------------
	// Page render
	// -------------------------------------------------------------------------

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
		?>
		<div class="wrap captchala-settings">
			<h1><?php echo esc_html__( 'CaptchaLa', 'captchala' ); ?></h1>
			<p class="description">
				<?php echo esc_html__( 'Privacy-first CAPTCHA + anti-spam. Smart CAPTCHA + anti-spam.', 'captchala' ); ?>
			</p>
			<form action="options.php" method="post">
				<?php
				settings_fields( 'captchala_settings_group' );
				do_settings_sections( self::PAGE_SLUG );
				submit_button();
				?>
			</form>
		</div>
		<?php
	}
}
