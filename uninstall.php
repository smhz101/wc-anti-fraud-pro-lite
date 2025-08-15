<?php
/**
 * Uninstall for WC Anti-Fraud Pro Lite
 */
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

// Remove main option
delete_option( 'wca_opts_ext' );

// Remove our transients (rate limits, bans)
global $wpdb;
$like  = $wpdb->esc_like( '_transient_wca_' ) . '%';
$like2 = $wpdb->esc_like( '_transient_timeout_wca_' ) . '%';
$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s", $like, $like2 ) );
