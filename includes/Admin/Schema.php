<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

function wca_fields_schema() {
	$D = wca_defaults();

	return array(
		'general'    => array(
			'title'  => __( 'General', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'enabled', 'checkbox', __( 'Enable anti-fraud engine', 'wc-anti-fraud-pro-lite' ), 1, __( 'Master switch. Disable to bypass all checks.', 'wc-anti-fraud-pro-lite' ) ),
				array( 'enable_logging', 'checkbox', __( 'Enable logging to Woo logs', 'wc-anti-fraud-pro-lite' ), 1, __( 'Writes JSON logs to WooCommerce → Status → Logs.', 'wc-anti-fraud-pro-lite' ) ),
			),
		),

		'validation' => array(
			'title'  => __( 'Data Validation', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'validation_profile', 'select', __( 'Validation Profile', 'wc-anti-fraud-pro-lite' ), 'generic', __( 'Choose a country/region preset. You can add your own regex as well.', 'wc-anti-fraud-pro-lite' ) ),
				array( 'validate_phone', 'checkbox', __( 'Validate phone format', 'wc-anti-fraud-pro-lite' ), 1, __( 'Checks phone against the profile regex OR your custom regex.', 'wc-anti-fraud-pro-lite' ) ),
                                array(
                                        'phone_regex',
                                        'text',
                                        __( 'Phone regex (PHP /.../ pattern)', 'wc-anti-fraud-pro-lite' ),
                                        '',
                                        __( 'Optional custom addition. Leave blank to use only the preset.', 'wc-anti-fraud-pro-lite' ),
                                        'depends' => 'validate_phone',
                                ),
                                array( 'validate_postal', 'checkbox', __( 'Validate postal/ZIP format', 'wc-anti-fraud-pro-lite' ), 1, __( 'Checks postal/ZIP against the profile regex OR your custom regex.', 'wc-anti-fraud-pro-lite' ) ),
                                array(
                                        'postal_regex',
                                        'text',
                                        __( 'Postal/ZIP regex (PHP /.../ pattern)', 'wc-anti-fraud-pro-lite' ),
                                        '',
                                        __( 'Optional custom addition. Leave blank to use only the preset.', 'wc-anti-fraud-pro-lite' ),
                                        'depends' => 'validate_postal',
                                ),
                                array( 'enable_reject_keywords', 'checkbox', __( 'Reject address keywords', 'wc-anti-fraud-pro-lite' ), 0, __( 'Block if address contains risky terms (e.g., P.O. Box).', 'wc-anti-fraud-pro-lite' ) ),
                                array(
                                        'reject_address_keywords',
                                        'textarea',
                                        __( 'Keywords list', 'wc-anti-fraud-pro-lite' ),
                                        $D['reject_address_keywords'],
                                        __( 'One per line. Example seed includes “PO Box, P.O. Box, suite, unit”.', 'wc-anti-fraud-pro-lite' ),
                                        'depends' => 'enable_reject_keywords',
                                ),
				// A placeholder field to render the live preview block (SettingsPage injects it)
				array( 'validation_preview', 'custom', __( 'Preview', 'wc-anti-fraud-pro-lite' ), '', '' ),
			),
		),

		'bot'        => array(
			'title'  => __( 'Bot & Session Checks', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'use_honeypots', 'checkbox', __( 'Use honeypot fields', 'wc-anti-fraud-pro-lite' ), 1, __( 'Static + rotating hidden inputs to trap bots.', 'wc-anti-fraud-pro-lite' ) ),
                                array( 'use_timestamp', 'checkbox', __( 'Require minimum render time', 'wc-anti-fraud-pro-lite' ), 1, __( 'Blocks submits faster than “Min seconds”.', 'wc-anti-fraud-pro-lite' ) ),
                                array( 'min_render_seconds', 'number', __( 'Min seconds between render & submit', 'wc-anti-fraud-pro-lite' ), 2, __( '2–3s recommended.', 'wc-anti-fraud-pro-lite' ), 'depends' => 'use_timestamp' ),
                                array( 'enable_device_age', 'checkbox', __( 'Require device cookie age', 'wc-anti-fraud-pro-lite' ), 1, __( 'Blocks if device first-seen cookie is too fresh. Cookie: wca_seen_ts', 'wc-anti-fraud-pro-lite' ) ),
                                array( 'device_min_age', 'number', __( 'Device min age (seconds)', 'wc-anti-fraud-pro-lite' ), 15, '', 'depends' => 'enable_device_age' ),
                                array( 'strict_ref', 'checkbox', __( 'Strict referrer check', 'wc-anti-fraud-pro-lite' ), 1, __( 'Blocks when HTTP referrer is empty or not your host.', 'wc-anti-fraud-pro-lite' ) ),
                                array( 'ua_blacklist', 'textarea', __( 'User-Agent blacklist', 'wc-anti-fraud-pro-lite' ), $D['ua_blacklist'], '' ),
                        ),
                ),

		'velocity'   => array(
			'title'  => __( 'Velocity & Bans', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'rate_ip_limit', 'number', __( 'Max attempts per IP / 15m', 'wc-anti-fraud-pro-lite' ), 3, '' ),
				array( 'rate_email_limit', 'number', __( 'Max attempts per email / 15m', 'wc-anti-fraud-pro-lite' ), 2, '' ),
				array( 'ban_minutes', 'number', __( 'Temp-ban minutes after block', 'wc-anti-fraud-pro-lite' ), 60, '' ),
				array( 'bans_table', 'custom', __( 'Temporary IP Bans', 'wc-anti-fraud-pro-lite' ), '', '' ),
			),
		),

		'geo'        => array(
			'title'  => __( 'Geography', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'allow_countries', 'text', __( 'Allow only these billing countries (CSV)', 'wc-anti-fraud-pro-lite' ), '', '' ),
				array( 'deny_countries', 'text', __( 'Deny these billing countries (CSV)', 'wc-anti-fraud-pro-lite' ), '', '' ),
			),
		),

		'cart'       => array(
			'title'  => __( 'Cart & Checkout', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				// product multi-select (custom renderer) + relation
				array(
					'flag_product_ids',
					'product_multi',
					__( 'Flag product IDs', 'wc-anti-fraud-pro-lite' ),
					'',
					__( 'Select products that are commonly abused for tests.', 'wc-anti-fraud-pro-lite' ),
				),
				array(
					'flag_match_mode',
					'select',
					__( 'Match mode (AND/OR)', 'wc-anti-fraud-pro-lite' ),
					'any',
					__( '"Any" = at least one selected product is in cart. "All" = all selected products must be present.', 'wc-anti-fraud-pro-lite' ),
				),

				array( 'block_if_only_flagged', 'checkbox', __( 'Block when cart contains only flagged IDs', 'wc-anti-fraud-pro-lite' ), 1, '' ),
				array(
					'low_value_threshold',
					'number_currency',
					__( 'Low-value threshold', 'wc-anti-fraud-pro-lite' ),
					'10.00',
					__( 'Require login below this amount (if enabled).', 'wc-anti-fraud-pro-lite' ),
				),
				array( 'require_login_below', 'checkbox', __( 'Require login if total below threshold', 'wc-anti-fraud-pro-lite' ), 1, '' ),
				array( 'block_guest_for_flagged', 'checkbox', __( 'Block guest checkout if cart matches flagged rule', 'wc-anti-fraud-pro-lite' ), 1, '' ),
			),
		),

		'gateways'   => array(
			'title'  => __( 'Gateways', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
                                array(
                                        'enable_gateway_friction',
                                        'checkbox',
                                        __( 'Enable gateway friction', 'wc-anti-fraud-pro-lite' ),
                                        0,
                                        __( 'When enabled, hide card gateways below a minimum total. Wallets like PayPal are unaffected unless you add their IDs.', 'wc-anti-fraud-pro-lite' ),
                                ),
                                array( 'min_total_for_card', 'text', __( 'Min order total to show card gateways', 'wc-anti-fraud-pro-lite' ), '10.00', '', 'depends' => 'enable_gateway_friction' ),
                                array( 'card_gateway_ids', 'textarea', __( 'Card gateway IDs (one per line)', 'wc-anti-fraud-pro-lite' ), "stripe\nwoo_stripe\nwoocommerce_payments", '', 'depends' => 'enable_gateway_friction' ),
                        ),
                ),

		'lists'      => array(
			'title'  => __( 'Lists / Blacklists', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'ip_whitelist', 'textarea', __( 'IP whitelist', 'wc-anti-fraud-pro-lite' ), '', '' ),
				array( 'ip_blacklist', 'textarea', __( 'IP blacklist', 'wc-anti-fraud-pro-lite' ), '', '' ),
				array( 'email_whitelist', 'textarea', __( 'Email/domain whitelist', 'wc-anti-fraud-pro-lite' ), '', '' ),
				array( 'email_blacklist', 'textarea', __( 'Email/domain blacklist', 'wc-anti-fraud-pro-lite' ), '', '' ),
				array( 'disposable_domains', 'textarea', __( 'Disposable domains', 'wc-anti-fraud-pro-lite' ), $D['disposable_domains'], '' ),
				array( 'block_disposable_email', 'checkbox', __( 'Block disposable email domains', 'wc-anti-fraud-pro-lite' ), 1, '' ),
			),
		),

		'messages'   => array(
			'title'  => __( 'Messages', 'wc-anti-fraud-pro-lite' ),
			'fields' => array(
				array( 'block_message', 'text', __( 'Generic block message', 'wc-anti-fraud-pro-lite' ), $D['block_message'], '' ),
			),
		),
	);
}
