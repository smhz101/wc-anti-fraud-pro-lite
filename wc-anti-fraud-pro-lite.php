<?php
/**
 * Plugin Name: WC Anti-Fraud Pro Lite
 * Plugin URI: https://example.com/
 * Description: Pre-checkout risk checks, structured logging, presets, and admin tools (modern tabbed UI).
 * Author: Muzammil Hussain
 * Version: 1.8.0
 * Requires PHP: 8.0
 * Requires at least: 6.0
 * Tested up to: 6.6
 * WC requires at least: 7.0
 * WC tested up to: 9.1
 * Text Domain: wc-anti-fraud-pro-lite
 * Domain Path: /languages
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( defined( 'WCA_PRO_LITE_ACTIVE' ) ) {
	return;
}
define( 'WCA_PRO_LITE_ACTIVE', '1.8.0' );

define( 'WCA_PLUGIN_FILE', __FILE__ );
define( 'WCA_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WCA_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'WCA_MENU_SLUG', 'wc-antifraud-pro-lite' );

function wca_load_textdomain() {
	load_plugin_textdomain( 'wc-anti-fraud-pro-lite', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );
}
add_action( 'init', 'wca_load_textdomain' );

/* Includes */
require_once WCA_PLUGIN_DIR . 'includes/Presets.php';
require_once WCA_PLUGIN_DIR . 'includes/Functions.php';
require_once WCA_PLUGIN_DIR . 'includes/Admin/Schema.php';
require_once WCA_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/FraudEngine.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/Telemetry.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/GatewayFriction.php';

/*
Hooks */
// Admin
add_action( 'admin_menu', 'wca_admin_menu' );
add_action( 'admin_init', 'wca_admin_init' );
add_action( 'admin_init', 'wca_handle_tools' );
add_action( 'admin_enqueue_scripts', 'wca_enqueue_admin_assets' );
add_action( 'update_option_wca_opts_ext', 'wca_on_settings_updated', 10, 2 );

// Import/Export
add_action( 'admin_post_wca_export', 'wca_export_settings' );
add_action( 'admin_post_wca_import', 'wca_import_settings' );

// Frontend
add_action( 'woocommerce_init', 'wca_wc_init' );
add_action( 'wp', 'wca_set_first_seen_cookie' );
add_action( 'woocommerce_after_checkout_billing_form', 'wca_checkout_fields' );
add_action( 'woocommerce_after_checkout_validation', 'wca_validate_checkout', 10, 2 );
add_action( 'woocommerce_checkout_create_order', 'wca_on_create_order', 10, 2 );
add_filter( 'woocommerce_available_payment_gateways', 'wca_filter_gateways', 20 );
add_action( 'admin_notices', 'wca_admin_notice_tip' );
