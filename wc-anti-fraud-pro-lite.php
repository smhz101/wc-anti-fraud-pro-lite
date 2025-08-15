<?php
/**
 * Plugin Name: WC Anti-Fraud Pro Lite
 * Plugin URI: https://muzammil.dev/
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
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/old-licenses/gpl-2.0.en.html
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
require_once WCA_PLUGIN_DIR . 'includes/Admin/LogsPage.php';
require_once WCA_PLUGIN_DIR . 'includes/Admin/DashboardPage.php';
// Admin pages (dashboard, logs, settings)
require_once WCA_PLUGIN_DIR . 'includes/Admin/SettingsPage.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/FraudEngine.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/Telemetry.php';
require_once WCA_PLUGIN_DIR . 'includes/Services/GatewayFriction.php';

/**
 * Bootstrap after plugins load & only if WooCommerce is present.
 */
add_action( 'plugins_loaded', 'wcaf_main_loader' );
function wcaf_main_loader() {
	if ( ! class_exists( 'WooCommerce' ) ) {
		return;
	}

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
}

/**
 * Declare compatibility with Woo features (HPOS, COGS, etc.).
 * - Runs before Woo init, as required by FeaturesUtil.
 * - Checks if each feature actually exists before declaring.
 */
add_action('before_woocommerce_init', 'wca_declare_woo_feature_compat');

function wca_declare_woo_feature_compat() {
	if ( ! class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
		return;
	}

	// Pull available features to avoid guessing slugs.
	$features = \Automattic\WooCommerce\Utilities\FeaturesUtil::get_features();

	// HPOS (High-Performance Order Storage).
	if ( isset( $features['custom_order_tables'] ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'custom_order_tables',
			__FILE__,
			true // compatible
		);
	}

	// COGS (Cost of Goods Sold) — introduced in WC 9.9+ and toggled under Advanced → Features.
	if ( isset( $features['cogs'] ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cogs',
			__FILE__,
			true
		);
	}

	// (Optional) Cart & Checkout Blocks — avoids unrelated “incompatible” lists.
	if ( isset( $features['cart_checkout_blocks'] ) ) {
		\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility(
			'cart_checkout_blocks',
			__FILE__,
			true
		);
	}
}