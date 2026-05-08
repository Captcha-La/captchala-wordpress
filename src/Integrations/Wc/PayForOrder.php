<?php
/**
 * Protect the WooCommerce "pay for order" page (failed-order recovery /
 * customer pay-link flow).
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

final class PayForOrder extends AbstractIntegration {

	public static function option_key(): string {
		return 'wc_pay_for_order';
	}

	protected function action(): string {
		return Action::PAY_FOR_ORDER;
	}

	public function init(): void {
		add_action( 'woocommerce_pay_order_before_submit', [ $this, 'render_field' ] );
		add_action( 'woocommerce_after_pay_action',        [ $this, 'validate_pay' ] );
	}

	public function render_field(): void {
		$this->print_widget();
	}

	public function validate_pay(): void {
		$result = $this->validate();
		if ( ! $result->isValid() && function_exists( 'wc_add_notice' ) ) {
			wc_add_notice( $this->plugin->error_message( $result->getError() ), 'error' );
		}
	}
}
