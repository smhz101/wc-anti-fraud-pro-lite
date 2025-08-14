<?php
if (!defined('ABSPATH')) exit;

/* WC session heartbeat */
function wca_wc_init() {
    if (function_exists('WC') && WC()->session) {
        WC()->session->set('wca_alive', 1);
    }
}

/* Device first-seen cookie (for device age rule) */
function wca_set_first_seen_cookie() {
    $o = wca_opt(); if (empty($o['enable_device_age'])) return;
    if (is_admin()) return;
    if (!isset($_COOKIE['wca_seen_ts'])) {
        setcookie('wca_seen_ts', (string) time(), time()+86400, COOKIEPATH ?: '/', $_SERVER['HTTP_HOST'] ?? '', is_ssl(), true);
    }
}

/* Inject honeypots + timestamp */
function wca_checkout_fields() {
    $o = wca_opt();
    if (empty($o['enabled']) || empty($o['use_honeypots'])) return;
    if (!function_exists('WC') || !WC()->session) return;

    $name = 'hp_' . wp_generate_password(6, false, false);
    WC()->session->set('wca_hp', $name);

    echo '<div style="display:none" aria-hidden="true"><input type="text" name="hp_static" id="hp_static" value=""></div>';
    echo '<div style="display:none" aria-hidden="true"><input type="text" name="'.esc_attr($name).'" id="'.esc_attr($name).'" value=""></div>';

    if (!empty($o['use_timestamp'])) {
        $ts = time(); WC()->session->set('wca_ts', $ts);
        echo '<input type="hidden" name="wca_ts" value="'.esc_attr($ts).'">';
    }
}

/* Helper: safe preg_match with PHP-pattern string */
function wca_match_php_regex($pattern, $value) {
    if (!$pattern || !is_string($pattern)) return null; // null => no decision
    // sanity check: avoid warnings if invalid delimiters
    set_error_handler(function(){}, E_WARNING);
    $ok = @preg_match($pattern, 'test');
    restore_error_handler();
    if ($ok === false) return null;
    return (bool) preg_match($pattern, (string)$value);
}

/* Validate checkout */
function wca_validate_checkout($data, $errors) {
    $o = wca_opt(); if (empty($o['enabled'])) return;

    $ip   = wca_ip();
    $ua   = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $ref  = $_SERVER['HTTP_REFERER'] ?? '';
    $host = $_SERVER['SERVER_NAME'] ?? '';

    if ($ip && in_array($ip, wca_lines_to_array($o['ip_whitelist']), true)) return;
    if ($ip && in_array($ip, wca_lines_to_array($o['ip_blacklist']), true)) {
        $errors->add('wca_block', __($o['block_message'], 'wc-anti-fraud-pro-lite'));
        wca_log_event('blocked', array('reasons'=>array('ip_blacklist'),'ip'=>$ip), 'warning');
        return;
    }
    if ($ip && wca_is_banned($ip)) {
        $errors->add('wca_block', __($o['block_message'], 'wc-anti-fraud-pro-lite'));
        wca_log_event('blocked', array('reasons'=>array('temp_ban'),'ip'=>$ip), 'warning');
        return;
    }

    $email   = isset($data['billing_email']) ? sanitize_email($data['billing_email']) : '';
    $country = isset($data['billing_country']) ? strtoupper(sanitize_text_field($data['billing_country'])) : '';
    $block=false; $reasons=array(); $checks=array();

    /** ---------------------------- */

    // Device age
    if (!empty($o['enable_device_age'])) {
        $seen = isset($_COOKIE['wca_seen_ts']) ? (int) $_COOKIE['wca_seen_ts'] : 0;
        $checks['device_age_ok'] = ($seen && (time() - $seen) >= (int) $o['device_min_age']);
        if (!$checks['device_age_ok']) { $block=true; $reasons[]='device_too_new'; }
    }

    /** ---------------------------- */

    // Email
    if ($email && !wca_email_in_list($email, $o['email_whitelist'])) {
        if ($email && wca_email_in_list($email, $o['email_blacklist'])) { $block=true; $reasons[]='email_blacklist'; }
        if (!empty($o['block_disposable_email']) && $email && wca_is_disposable($email)) { $block=true; $reasons[]='disposable_email'; }
    }

    /** ---------------------------- */

    // Honeypots / timestamp
    if (!empty($o['use_honeypots'])) {
        if (!empty($_POST['hp_static'])) { $block=true; $reasons[]='static_hp'; }
        $rot = (function_exists('WC') && WC()->session) ? WC()->session->get('wca_hp') : null;
        if ($rot && !empty($_POST[$rot])) { $block=true; $reasons[]='rot_hp'; }
    }
    if (!empty($o['use_timestamp'])) {
        $ts_srv = (function_exists('WC') && WC()->session) ? (int) WC()->session->get('wca_ts') : 0;
        $ts_cli = isset($_POST['wca_ts']) ? (int) $_POST['wca_ts'] : 0;
        $checks['render_time_ok'] = ($ts_srv && $ts_cli && $ts_cli === $ts_srv && (time() - $ts_srv) >= (int)$o['min_render_seconds']);
        if (!$checks['render_time_ok']) { $block=true; $reasons[]='fast_submit'; }
    }

    /** ---------------------------- */

    // Referrer / UA
    if (!empty($o['strict_ref'])) {
        $checks['referrer_ok'] = (!empty($ref) && stripos($ref,$host) !== false);
        if (!$checks['referrer_ok']) { $block=true; $reasons[]='bad_referrer'; }
    }
    if ($ua && wca_bad_ua($ua)) { $block=true; $reasons[]='bad_ua'; }

    /** ---------------------------- */

    // Geography
    if ($country) {
        $allow = wca_csv_to_array($o['allow_countries']);
        $deny  = wca_csv_to_array($o['deny_countries']);
        if (!empty($allow) && !in_array($country,$allow,true)) { $block=true; $reasons[]='country_not_allowed'; }
        if (!empty($deny)  &&  in_array($country,$deny,true))   { $block=true; $reasons[]='country_denied'; }
    }

    /** ---------------------------- */

    // Velocity
    $limit_ip = max(0,(int)$o['rate_ip_limit']);
    $limit_em = max(0,(int)$o['rate_email_limit']);
    if ($limit_ip > 0 && $ip) {
        $k = 'wca_ip_'.md5($ip); $v = (int) get_transient($k);
        if ($v >= $limit_ip) { $block=true; $reasons[]='ip_rate'; }
        set_transient($k,$v+1,15*MINUTE_IN_SECONDS);
    }
    if ($limit_em > 0 && $email) {
        $k = 'wca_em_'.md5($email); $v = (int) get_transient($k);
        if ($v >= $limit_em) { $block=true; $reasons[]='email_rate'; }
        set_transient($k,$v+1,15*MINUTE_IN_SECONDS);
    }

    /** ---------------------------- */

    // Cart rules
    $cart_total = (function_exists('WC') && WC()->cart) ? (float) WC()->cart->get_total('edit') : 0.0;
    $flag_ids   = array_values(array_filter(array_map('intval', explode(',', (string)($o['flag_product_ids'] ?? '')))));
    $cart_pids  = array();
    if (function_exists('WC') && WC()->cart) {
        foreach (WC()->cart->get_cart() as $item) {
            $cart_pids[] = (int) ($item['product_id'] ?? 0);
        }
    }

    // has ANY / has ALL (based on selection)
    $has_any = false; $has_all = false;
    if (!empty($flag_ids)) {
        $intersect = array_intersect($flag_ids, $cart_pids);
        $has_any   = count($intersect) > 0;
        $has_all   = empty(array_diff($flag_ids, $cart_pids)); // all selected are present
    }

    // only-flagged (original behavior)
    $only_flag = false;
    if (!empty($flag_ids) && !empty($cart_pids)) {
        $only_flag = count(array_diff($cart_pids, $flag_ids)) === 0; // every cart item is flagged
    }
    if (!empty($o['block_if_only_flagged']) && $only_flag) {
        $block = true; $reasons[] = 'flagged_product_only';
    }

    // guest rule uses relation
    $match_mode = ($o['flag_match_mode'] ?? 'any') === 'all' ? 'all' : 'any';
    $matches = ($match_mode === 'all') ? $has_all : $has_any;

    $is_guest = !is_user_logged_in();
    if (!empty($o['block_guest_for_flagged']) && $matches && $is_guest) {
        $block = true; $reasons[] = 'guest_flagged_requires_login';
    }

    // low-value rule (unchanged)
    if (!empty($o['require_login_below']) && $cart_total>0 && $cart_total < (float)$o['low_value_threshold'] && $is_guest) {
        $block = true; $reasons[] = 'guest_low_value_requires_login';
    }

    /** ---------------------------- */

    // Profile + custom regex validation
    $profile_key = isset($o['validation_profile']) ? $o['validation_profile'] : 'generic';
    $presets = wca_presets();
    $profile = $presets[ $profile_key ] ?? $presets['generic'];

    if (!empty($o['validate_phone'])) {
        $preset_ok = wca_match_php_regex($profile['phone'] ?? '', $data['billing_phone'] ?? '');
        $custom_ok = wca_match_php_regex($o['phone_regex'] ?? '', $data['billing_phone'] ?? '');
        // Decision: preset OR custom (if both null => skip)
        if ($preset_ok !== null || $custom_ok !== null) {
            $checks['phone_ok'] = ($preset_ok === true) || ($custom_ok === true);
            if (!$checks['phone_ok']) { $block=true; $reasons[]='phone_invalid'; }
        }
    }

    if (!empty($o['validate_postal'])) {
        $preset_ok = wca_match_php_regex($profile['postal'] ?? '', $data['billing_postcode'] ?? '');
        $custom_ok = wca_match_php_regex($o['postal_regex'] ?? '', $data['billing_postcode'] ?? '');
        if ($preset_ok !== null || $custom_ok !== null) {
            $checks['postal_ok'] = ($preset_ok === true) || ($custom_ok === true);
            if (!$checks['postal_ok']) { $block=true; $reasons[]='postal_invalid'; }
        }
    }

    if (!empty($o['enable_reject_keywords'])) {
        $addr1 = strtolower($data['billing_address_1'] ?? '');
        $addr2 = strtolower($data['billing_address_2'] ?? '');
        foreach (wca_lines_to_array($o['reject_address_keywords']) as $kw) {
            $kw = strtolower($kw);
            if ($kw !== '' && (strpos($addr1,$kw)!==false || strpos($addr2,$kw)!==false)) { $block=true; $reasons[]='addr_keyword:'.$kw; break; }
        }
    }

    /** ---------------------------- */

    if ($block) {
        $errors->add('wca_block', __($o['block_message'],'wc-anti-fraud-pro-lite'));
        wca_log_event('blocked', array(
            'reasons'=>$reasons,'email'=>$email,'country'=>$country,'ip'=>$ip,
            'profile'=>$profile_key,'checks'=>$checks,
        ), 'warning');
        if ($ip) wca_ban_ip($ip);
        return;
    }

    wca_log_event('pass', array('email'=>$email,'country'=>$country,'ip'=>$ip,'profile'=>$profile_key,'checks'=>$checks), 'info');
}