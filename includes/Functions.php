<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/** Versioned defaults + getters */
function wca_defaults() {
	return array(
		// Core toggles
		'enabled'                 => 1,
		'enable_logging'          => 1,

		// Bot / speed
		'use_honeypots'           => 1,
		'use_timestamp'           => 1,
		'min_render_seconds'      => 2,
		'enable_device_age'       => 1,
		'device_min_age'          => 15, // seconds

		// UA / Referrer / email
		'strict_ref'              => 1,
		'ua_blacklist'            => "curl\npython-requests\nlibwww-perl\nwget\nhttpclient\njava\nokhttp\ngo-http-client",
		'block_disposable_email'  => 1,
		'disposable_domains'      => "mailinator.com\n10minutemail.com\ntempmail.com\nyopmail.com\nguerillamail.com",

		// Velocity / bans
		'rate_ip_limit'           => 3,
		'rate_email_limit'        => 2,
		'ban_minutes'             => 60,

		// Geography
		'allow_countries'         => '',
		'deny_countries'          => '',

		// Cart & checkout
		'flag_product_ids'        => '',
		'block_if_only_flagged'   => 1,
		'low_value_threshold'     => 10.00,
		'require_login_below'     => 1,
		'block_guest_for_flagged' => 1,

		// Validation (global, not UK-only)
		'validation_profile'      => 'auto', // auto|generic|us|uk|ca|au|eu
		'validate_phone'          => 1,
		'phone_regex'             => '',     // user extra (OR with preset)
		'validate_postal'         => 1,
		'postal_regex'            => '',     // user extra (OR with preset)
		'enable_reject_keywords'  => 1,
		// sensible PO Box defaults
		'reject_address_keywords' => "po box\np.o. box\np o box\npobox\npost office box\nlocker",

		// Gateway friction (opt-in!)
		'enable_gateway_friction' => 0,
		'min_total_for_card'      => 10.00,
		'card_gateway_ids'        => "stripe\nwoo_stripe\nwoocommerce_payments",

		// Lists
		'ip_whitelist'            => '',
		'ip_blacklist'            => '',
		'email_whitelist'         => '',
		'email_blacklist'         => '',

		// Messages
		'block_message'           => 'Suspicious activity detected. Please contact support.',
	);
}

function wca_opt( $key = null ) {
	$opts   = get_option( 'wca_opts_ext', array() );
	$merged = array_merge( wca_defaults(), is_array( $opts ) ? $opts : array() );
	return $key === null ? $merged : ( isset( $merged[ $key ] ) ? $merged[ $key ] : null );
}

/* ----------- General helpers ----------- */
function wca_lines_to_array( $text ) {
	$out = array();
	foreach ( explode( "\n", (string) $text ) as $line ) {
		$line = trim( $line );
		if ( $line !== '' ) {
			$out[] = $line;
		}
	}
	return $out;
}
function wca_csv_to_array( $csv ) {
	$out = array();
	foreach ( explode( ',', (string) $csv ) as $p ) {
		$p = trim( $p );
		if ( $p !== '' ) {
			$out[] = $p;
		}
	}
	return $out;
}

/* ----------- IP/UA/etc ----------- */
function wca_ip() {
	$keys = array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'HTTP_X_REAL_IP', 'REMOTE_ADDR' );
	foreach ( $keys as $k ) {
		if ( ! empty( $_SERVER[ $k ] ) ) {
			$raw = trim( (string) $_SERVER[ $k ] );
			if ( $k === 'HTTP_X_FORWARDED_FOR' && strpos( $raw, ',' ) !== false ) {
				foreach ( array_map( 'trim', explode( ',', $raw ) ) as $p ) {
					if ( filter_var( $p, FILTER_VALIDATE_IP ) ) {
						return $p;
					}
				}
			}
			if ( filter_var( $raw, FILTER_VALIDATE_IP ) ) {
				return $raw;
			}
		}
	}
	return '';
}

/* ----------- Logging (structured) ----------- */
function wca_request_id() {
	static $rid = null;
	if ( $rid === null ) {
		$rid = substr( bin2hex( random_bytes( 8 ) ), 0, 12 ); }
	return $rid;
}
function wca_redact_email( $email ) {
	$email = strtolower( trim( (string) $email ) );
	if ( ! $email || strpos( $email, '@' ) === false ) {
		return $email;
	}
	list($u, $d) = explode( '@', $email, 2 );
	return substr( $u, 0, 1 ) . '***@' . $d;
}
function wca_redact_ip( $ip ) {
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4 ) ) {
		$parts    = explode( '.', $ip );
		$parts[3] = 'x';
		return implode( '.', $parts );
	}
	if ( filter_var( $ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6 ) ) {
		return substr( $ip, 0, 16 ) . '::xxxx';
	}
	return $ip;
}
function wca_common_ctx() {
        $uid   = get_current_user_id();
        $ua    = sanitize_text_field( $_SERVER['HTTP_USER_AGENT'] ?? '' );
        $ref   = esc_url_raw( $_SERVER['HTTP_REFERER'] ?? '' );
	$ip    = wca_ip();
	$total = 0.0;
	$items = array();
	if ( function_exists( 'WC' ) && WC()->cart ) {
		$total = (float) WC()->cart->get_total( 'edit' );
		foreach ( WC()->cart->get_cart() as $it ) {
			$items[] = array(
				'pid' => (int) ( $it['product_id'] ?? 0 ),
				'qty' => (int) ( $it['quantity'] ?? 1 ),
			);
		}
	}
	return array(
		'rid'   => wca_request_id(),
		'ip'    => $ip ? wca_redact_ip( $ip ) : '',
		'ua'    => $ua,
		'ref'   => $ref,
		'uid'   => $uid ? (int) $uid : 0,
		'guest' => $uid ? 0 : 1,
		'total' => $total,
		'items' => $items,
		'time'  => time(),
	);
}
function wca_log_event( $event, array $data = array(), $level = 'info' ) {
	if ( ! function_exists( 'wc_get_logger' ) ) {
		return;
	}
	$o = wca_opt();
	if ( empty( $o['enable_logging'] ) ) {
		return;
	}
	if ( isset( $data['email'] ) ) {
		$data['email'] = wca_redact_email( $data['email'] );
	}
	if ( isset( $data['ip'] ) ) {
		$data['ip'] = wca_redact_ip( $data['ip'] );
	}
	if ( isset( $data['order_id'] ) ) {
		$data['order_id'] = (int) $data['order_id'];
	}
        $payload = array_merge( array( 'event' => (string) $event ), wca_common_ctx(), $data );
        $json    = wp_json_encode( $payload, JSON_UNESCAPED_SLASHES );
        if ( ! is_string( $json ) ) {
                $json = wp_json_encode( array( 'event' => (string) $event, 'error' => 'json_encode_failed' ) );
                if ( ! is_string( $json ) ) {
                        return;
                }
        }
        if ( strlen( $json ) > 8000 ) {
                $json = substr( $json, 0, 8000 ) . '...';
        }
        wc_get_logger()->log( $level, $json, array( 'source' => 'wc-antifraud-pro-lite' ) );
}

/* ----------- Lists & UA ----------- */
function wca_email_in_list( $email, $list_text ) {
	$email  = strtolower( (string) $email );
	$domain = ( $pos = strrchr( $email, '@' ) ) ? strtolower( substr( $pos, 1 ) ) : '';
	foreach ( wca_lines_to_array( $list_text ) as $line ) {
		$line = strtolower( $line );
		if ( $line === $email || ( $domain && $line === $domain ) ) {
			return true;
		}
	}
	return false;
}
function wca_is_disposable( $email ) {
	$domain = ( $pos = strrchr( (string) $email, '@' ) ) ? strtolower( substr( $pos, 1 ) ) : '';
	return $domain ? in_array( $domain, wca_lines_to_array( wca_opt( 'disposable_domains' ) ), true ) : false;
}
function wca_bad_ua( $ua ) {
	$ua = strtolower( (string) $ua );
	foreach ( wca_lines_to_array( wca_opt( 'ua_blacklist' ) ) as $needle ) {
		if ( $needle !== '' && strpos( $ua, strtolower( $needle ) ) !== false ) {
			return true;
		}
	}
	return false;
}

/* ----------- Bans ----------- */
function wca_ban_ip( $ip ) {
	if ( ! $ip ) {
		return;
	}
	set_transient( 'wca_ban_' . md5( $ip ), 1, max( 1, (int) wca_opt( 'ban_minutes' ) ) * MINUTE_IN_SECONDS );
}
function wca_is_banned( $ip ) {
	return (bool) get_transient( 'wca_ban_' . md5( $ip ) ); }

/* Get list of current temp-bans (hash + seconds_left) */
function wca_list_bans() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_wca_ban_%' ORDER BY option_name" );
	$out  = array();
	foreach ( $rows as $r ) {
		$hash  = substr( $r->option_name, strlen( '_transient_wca_ban_' ) );
		$ttl   = get_option( '_transient_timeout_wca_ban_' . $hash );
		$left  = $ttl ? max( 0, (int) $ttl - time() ) : 0;
		$out[] = array(
			'hash'         => $hash,
			'seconds_left' => $left,
		);
	}
	return $out;
}

/* Map ISO billing country to a preset key (for "auto" profile) */
function wca_profile_for_country( $country ) {
	$c = strtoupper( (string) $country );
	if ( $c === 'GB' || $c === 'UK' || $c === 'IM' ) {
		return 'uk';
	}
	if ( $c === 'US' ) {
		return 'us';
	}
	if ( $c === 'CA' ) {
		return 'ca';
	}
	if ( $c === 'AU' || $c === 'NZ' ) {
		return 'au';
	}
	if ( in_array( $c, array( 'FR', 'DE', 'IT', 'ES', 'PT', 'NL', 'BE', 'AT', 'SE', 'DK', 'FI', 'IE', 'PL', 'CZ', 'SK', 'HU', 'RO', 'GR', 'BG', 'HR', 'SI', 'EE', 'LV', 'LT', 'LU', 'MT', 'CY' ), true ) ) {
		return 'eu';
	}
	return 'generic';
}
