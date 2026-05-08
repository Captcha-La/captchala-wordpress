<?php
/**
 * Protect the WooCommerce checkout page (both classic shortcode and
 * Block-based checkout).
 *
 * @package Captchala\Wp
 */

declare( strict_types=1 );

namespace Captchala\Wp\Integrations\Wc;

use Captchala\Cms\Action;
use Captchala\Wp\Integrations\AbstractIntegration;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class Checkout extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_checkout';
	}

	protected function action(): string {
		return Action::CHECKOUT;
	}

	public function init(): void {
		// Classic shortcode checkout
		add_action( 'woocommerce_review_order_before_submit', [ $this, 'render_field' ], 10 );
		add_action( 'woocommerce_checkout_process',           [ $this, 'validate_checkout' ] );

		// Block-based checkout — render before the actions block.
		add_filter( 'render_block_woocommerce/checkout-actions-block', [ $this, 'render_block_actions' ], 10, 1 );
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', [ $this, 'validate_block_checkout' ], 10, 2 );

		// Store API extension wiring so the React block-checkout can ship our
		// pt_token in `extensions.captchala.token`. Backend registration here;
		// front-end push happens via the small inline JS below that listens
		// for our captchala:success event and dispatches into wc.wcBlocksData.
		add_action( 'woocommerce_blocks_loaded',           [ $this, 'register_store_api_extension' ] );
		add_action( 'wp_enqueue_scripts',                  [ $this, 'enqueue_block_bridge' ] );
	}

	public function register_store_api_extension(): void {
		if ( ! function_exists( 'woocommerce_store_api_register_endpoint_data' ) ) {
			return;
		}
		woocommerce_store_api_register_endpoint_data(
			[
				'endpoint'        => 'checkout',
				'namespace'       => 'captchala',
				'data_callback'   => static function () { return [ 'token' => '' ]; },
				'schema_callback' => static function () {
					return [
						'token' => [
							'description' => 'CaptchaLa pass token (pt_) returned by the browser SDK.',
							'type'        => 'string',
							'context'     => [],
							'readonly'    => false,
						],
					];
				},
				'schema_type'     => ARRAY_A,
			]
		);
	}

	/**
	 * Tiny vanilla-JS bridge enqueued on the block-checkout page only.
	 * Listens for the captchala:success custom event our Widget bootstrap
	 * dispatches, then pushes the resolved pt_token into Woo's checkout
	 * data store under our `captchala` extension namespace — so the Store
	 * API request body carries `extensions.captchala.token`.
	 */
	public function enqueue_block_bridge(): void {
		// Old Woo (< 6.5) doesn't ship the block-checkout flavour at all and
		// won't register the wc-blocks-checkout script handle. Bail.
		if ( ! function_exists( 'has_block' ) || ! function_exists( 'is_checkout' ) ) {
			return;
		}
		if ( ! wp_script_is( 'wc-blocks-checkout', 'registered' ) && ! wp_script_is( 'wc-blocks-checkout', 'enqueued' ) ) {
			return;
		}
		// Only emit on the checkout page — and only when the block flavour is in use.
		$post              = is_singular() ? get_post() : null;
		$content           = $post ? (string) $post->post_content : '';
		$has_str_contains  = function_exists( 'str_contains' );
		$has_block_marker  = $has_str_contains ? str_contains( $content, '<!-- wp:woocommerce/checkout' ) : ( strpos( $content, '<!-- wp:woocommerce/checkout' ) !== false );
		$is_block_checkout = is_checkout() && ( has_block( 'woocommerce/checkout' ) || $has_block_marker );
		if ( ! $is_block_checkout ) {
			return;
		}

		$script = <<<'JS'
(function(){
  function tryPush(token){
    try{
      if(window.wp && window.wp.data && window.wp.data.dispatch){
        var d=window.wp.data.dispatch('wc/store/checkout');
        if(d && d.setExtensionData){
          // 2-arg form (namespace, object) is supported across all Woo blocks
          // versions and unambiguously stores extensions.captchala = {token}.
          d.setExtensionData('captchala', { token: token || '' });
          return true;
        }
      }
    }catch(e){}
    return false;
  }
  document.addEventListener('captchala:success', function(ev){
    var token=(ev && ev.detail && ev.detail.token) || '';
    var ok=tryPush(token);
    if(!ok){
      // wc.wcBlocksData store not ready yet — retry briefly until React mounts.
      var tries=0;
      var t=setInterval(function(){ tries++; if(tryPush(token) || tries>40){clearInterval(t);} }, 150);
    }
  });
})();
JS;
		wp_add_inline_script( 'wc-blocks-checkout', $script );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	public function render_block_actions( string $block_content ): string {
		// Inject the widget right before the Place Order button block.
		return $this->render() . $block_content;
	}

	public function validate_checkout(): void {
		$result = $this->validate();
		if ( ! $result->isValid() && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->plugin->error_message( $result->getError() ), 'error' );
		}
	}

	/**
	 * Block checkout validation. Reads the pt_token that
	 * register_store_api_extension() declared as an accepted extension field;
	 * the React-side bridge enqueue_block_bridge() pushes our captchala:success
	 * token into wc/store/checkout's setExtensionData('captchala', 'token', ...)
	 * so Woo serializes it into request body extensions.captchala.token.
	 *
	 * Errors surface as Woo's RouteException (clean Store API 400) on Woo 8+,
	 * \Exception fallback on older Woo.
	 *
	 * @param mixed $order
	 * @param mixed $request \WP_REST_Request
	 */
	public function validate_block_checkout( $order, $request ): void {
		// Trusted-role bypass.
		if ( $this->plugin->should_skip_for_user() ) {
			return;
		}

		$token = '';
		if ( $request && is_callable( [ $request, 'get_param' ] ) ) {
			$ext = $request->get_param( 'extensions' );
			if ( is_array( $ext ) && isset( $ext['captchala']['token'] ) ) {
				$token = sanitize_text_field( (string) $ext['captchala']['token'] );
			}
		}

		$result  = $this->plugin->client()->validate( $token, false, $this->plugin->remote_ip() );
		if ( $result->isValid() ) {
			return;
		}

		$message = $this->plugin->error_message( $result->getError() );

		if ( class_exists( '\Automattic\WooCommerce\StoreApi\Exceptions\RouteException' ) ) {
			throw new \Automattic\WooCommerce\StoreApi\Exceptions\RouteException(
				'captchala_failed',
				esc_html( $message ),
				400
			);
		}
		throw new \Exception( esc_html( $message ) );
	}
}
