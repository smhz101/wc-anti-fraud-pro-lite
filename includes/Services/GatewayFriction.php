<?php
if (!defined('ABSPATH')) exit;

/**
 * Hide *card* gateways under a minimum total when enabled.
 * PayPal/Wallets remain visible unless admin explicitly lists their IDs.
 */
function wca_filter_gateways($gateways) {
    $o = wca_opt();
    if (empty($o['enabled']) || empty($o['enable_gateway_friction']) || !function_exists('WC') || !WC()->cart) {
        return $gateways;
    }

    $total = (float) WC()->cart->total;
    $min   = (float) $o['min_total_for_card'];
    $ids   = wca_lines_to_array($o['card_gateway_ids']);

    if ($min > 0 && $total > 0 && $total < $min && !empty($ids)) {
        $removed = array();
        foreach ($ids as $gid) {
            $gid = trim($gid);
            if (isset($gateways[$gid])) { unset($gateways[$gid]); $removed[] = $gid; }
        }
        if ($removed) {
            wca_log_event('gateways_hidden', array('min_total'=>$min,'total'=>$total,'gateways'=>$removed), 'info');
        }
    }
    return $gateways;
}