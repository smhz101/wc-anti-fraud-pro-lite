<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Persist IP/UA/referrer on order creation + log event */
function wca_on_create_order( $order, $data ) {
	$ip  = wca_ip();
	$ua  = isset( $_SERVER['HTTP_USER_AGENT'] ) ? sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ) : '';
	$ref = isset( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( $_SERVER['HTTP_REFERER'] ) : 'None';
	if ( $ip ) {
		$order->set_customer_ip_address( $ip );
	}
	if ( $ua ) {
		$order->update_meta_data( '_customer_user_agent', $ua );
	}
	$order->update_meta_data( '_customer_referrer', $ref ?: 'None' );

	wca_log_event(
		'order_created',
		array(
			'order_id' => (int) $order->get_id(),
			'ip'       => $ip,
		),
		'info'
	);
}

/* Admin notice tip */
function wca_admin_notice_tip() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	$o = wca_opt();
	if ( ! empty( $o['strict_ref'] ) ) {
		echo '<div class="notice notice-info"><p><strong>Anti-Fraud:</strong> ' . esc_html__( 'Strict referrer is enabled. If legit customers report blocks (ITP/Privacy), raise timestamp/device-age or disable referrer check.', 'wc-anti-fraud-pro-lite' ) . '</p></div>';
	}
}
