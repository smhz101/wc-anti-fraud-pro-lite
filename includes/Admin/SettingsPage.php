<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/* Register setting */
function wca_admin_init() {
	register_setting( 'wca_group_ext', 'wca_opts_ext', array( 'sanitize_callback' => 'wca_sanitize_opts_ext' ) );
}

/* Admin menu */
function wca_admin_menu() {
	add_submenu_page(
		'woocommerce',
		__( 'Anti-Fraud', 'wc-anti-fraud-pro-lite' ),
		__( 'Anti-Fraud', 'wc-anti-fraud-pro-lite' ),
		'manage_woocommerce',
               WCA_MENU_SLUG,
               'wca_admin_page'
        );
}

/* Enqueue admin assets only on our page */
function wca_enqueue_admin_assets( $hook ) {
       if ( $hook !== 'woocommerce_page_' . WCA_MENU_SLUG ) {
               return;
       }

       $section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dashboard';

       wp_enqueue_style( 'wca-admin', WCA_PLUGIN_URL . 'assets/admin.css', array(), WCA_PRO_LITE_ACTIVE );

       $deps = array( 'jquery' );

       if ( $section === 'dashboard' ) {
               wp_enqueue_script( 'wca-chart', WCA_PLUGIN_URL . 'assets/chart.min.js', array(), '4.4.0', true );
               $deps[] = 'wca-chart';
       }

       wp_enqueue_script( 'wca-admin', WCA_PLUGIN_URL . 'assets/admin.js', $deps, WCA_PRO_LITE_ACTIVE, true );

        // SelectWoo (WooCommerce’s Select2 fork)
        wp_enqueue_script( 'selectWoo' );           // provided by WooCommerce

        // CSS — load WooCommerce's own SelectWoo styling
        wp_enqueue_style(
                'selectWoo',
                WC()->plugin_url() . '/assets/css/select2.css',
                array(),
                WC_VERSION
        );

	// Localize presets + ajax for product search + currency
	$presets = function_exists( 'wca_presets' ) ? wca_presets() : array();
	$opts    = wca_opt();

	wp_localize_script(
		'wca-admin',
		'WCA_PRESETS',
		array(
			'presets'  => $presets,
			'opts'     => $opts,
			'ajax'     => array(
				'url'      => admin_url( 'admin-ajax.php' ),
				'action'   => 'woocommerce_json_search_products_and_variations',
				'security' => wp_create_nonce( 'search-products' ),
			),
			'currency' => array(
				'symbol' => function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '',
			),
			'i18n'     => array(
				'matches' => __( 'Matches', 'wc-anti-fraud-pro-lite' ),
				'fails'   => __( 'Fails', 'wc-anti-fraud-pro-lite' ),
			),
		)
	);
}

/* Sanitizer */
function wca_sanitize_opts_ext( $input ) {
	$in  = is_array( $input ) ? wp_unslash( $input ) : array();
	$def = wca_defaults();
	$out = array();

	// checkboxes
	$checkboxes = array(
		'enabled',
		'enable_logging',
		'use_honeypots',
		'use_timestamp',
		'strict_ref',
		'block_disposable_email',
		'block_if_only_flagged',
		'require_login_below',
		'block_guest_for_flagged',
		'enable_device_age',
		'validate_phone',
		'validate_postal',
		'enable_reject_keywords',
		'enable_gateway_friction',
	);
	foreach ( $checkboxes as $k ) {
		$out[ $k ] = isset( $in[ $k ] ) ? 1 : 0; }

	// numbers (ints)
	foreach ( array( 'rate_ip_limit', 'rate_email_limit', 'ban_minutes', 'min_render_seconds', 'device_min_age' ) as $k ) {
		$out[ $k ] = isset( $in[ $k ] ) ? max( 0, (int) $in[ $k ] ) : (int) $def[ $k ];
	}

	// floats
	foreach ( array( 'low_value_threshold', 'min_total_for_card' ) as $k ) {
		$val       = isset( $in[ $k ] ) ? $in[ $k ] : ( $def[ $k ] ?? '0' );
		$out[ $k ] = is_numeric( $val ) ? (float) $val : (float) $def[ $k ];
	}

	// select values
	$out['validation_profile'] = isset( $in['validation_profile'] ) ? sanitize_text_field( $in['validation_profile'] ) : ( $def['validation_profile'] ?? 'generic' );
	$out['flag_match_mode']    = isset( $in['flag_match_mode'] ) ? ( in_array( $in['flag_match_mode'], array( 'any', 'all' ), true ) ? $in['flag_match_mode'] : 'any' ) : 'any';

	// product multiselect: accept array or CSV
	if ( isset( $in['flag_product_ids'] ) ) {
		if ( is_array( $in['flag_product_ids'] ) ) {
			$ids                     = array_map( 'intval', $in['flag_product_ids'] );
			$ids                     = array_values( array_filter( $ids ) );
			$out['flag_product_ids'] = implode( ',', $ids );
		} else {
			$out['flag_product_ids'] = sanitize_text_field( $in['flag_product_ids'] ); // fallback
		}
	} else {
		$out['flag_product_ids'] = '';
	}

        // text & textarea…
        foreach ( array( 'allow_countries', 'deny_countries', 'flag_product_ids', 'block_message' ) as $k ) {
                if ( ! isset( $out[ $k ] ) ) {
                        $out[ $k ] = isset( $in[ $k ] ) ? sanitize_text_field( $in[ $k ] ) : (string) ( $def[ $k ] ?? '' );
                }
        }
       foreach ( array( 'phone_regex', 'postal_regex' ) as $k ) {
               if ( ! isset( $out[ $k ] ) ) {
                       $raw       = isset( $in[ $k ] ) ? wp_unslash( $in[ $k ] ) : '';
                       $out[ $k ] = trim( wp_strip_all_tags( $raw ) );
               }
       }
	foreach ( array( 'ua_blacklist', 'disposable_domains', 'reject_address_keywords', 'ip_whitelist', 'ip_blacklist', 'email_whitelist', 'email_blacklist', 'card_gateway_ids' ) as $k ) {
		$val       = isset( $in[ $k ] ) ? (string) $in[ $k ] : (string) ( $def[ $k ] ?? '' );
		$val       = preg_replace( "/\r\n?/", "\n", $val );
		$out[ $k ] = trim( $val );
	}

	return $out;
}

/* Main admin page router */
function wca_admin_page() {
        $section = isset( $_GET['section'] ) ? sanitize_key( $_GET['section'] ) : 'dashboard';
        $allowed = array( 'dashboard', 'logs', 'settings' );
        if ( ! in_array( $section, $allowed, true ) ) {
                $section = 'dashboard';
        }

        $base = menu_page_url( WCA_MENU_SLUG, false );
        $schema = wca_fields_schema();
        $opts   = wca_opt();
        $tab    = isset( $_GET['tab'] ) ? sanitize_key( $_GET['tab'] ) : 'general';
        if ( ! isset( $schema[ $tab ] ) ) {
                $tab = 'general';
        }
        ?>
        <div class="wrap wca-wrap">
                <h1><?php esc_html_e( 'WooCommerce Anti-Fraud (Lite)', 'wc-anti-fraud-pro-lite' ); ?></h1>

                <nav class="wca-top-nav">
                        <a href="<?php echo esc_url( add_query_arg( 'section', 'dashboard', $base ) ); ?>" class="<?php echo $section === 'dashboard' ? 'current' : ''; ?>"><?php esc_html_e( 'Dashboard', 'wc-anti-fraud-pro-lite' ); ?></a>
                        <a href="<?php echo esc_url( add_query_arg( 'section', 'logs', $base ) ); ?>" class="<?php echo $section === 'logs' ? 'current' : ''; ?>"><?php esc_html_e( 'Logs', 'wc-anti-fraud-pro-lite' ); ?></a>
                        <a href="<?php echo esc_url( add_query_arg( 'section', 'settings', $base ) ); ?>" class="<?php echo $section === 'settings' ? 'current' : ''; ?>"><?php esc_html_e( 'Settings', 'wc-anti-fraud-pro-lite' ); ?></a>
                </nav>

                <?php if ( $section === 'settings' ) : ?>
                        <div class="wca-header">
                                <div class="wca-actions">
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wca-inline">
                                                <?php wp_nonce_field( 'wca_export_nonce', 'wca_export_nonce' ); ?>
                                                <input type="hidden" name="action" value="wca_export">
                                                <button class="button"><?php esc_html_e( 'Export', 'wc-anti-fraud-pro-lite' ); ?></button>
                                        </form>
                                        <form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wca-inline" enctype="multipart/form-data">
                                                <?php wp_nonce_field( 'wca_import_nonce', 'wca_import_nonce' ); ?>
                                                <input type="hidden" name="action" value="wca_import">
                                                <label class="wca-file">
                                                        <input type="file" name="wca_file" accept="application/json" />
                                                        <span><?php esc_html_e( 'Choose JSON', 'wc-anti-fraud-pro-lite' ); ?></span>
                                                </label>
                                                <button class="button button-primary"><?php esc_html_e( 'Import', 'wc-anti-fraud-pro-lite' ); ?></button>
                                        </form>
                                        <input type="search" id="wca-search" class="wca-search" placeholder="<?php esc_attr_e( 'Search settings…', 'wc-anti-fraud-pro-lite' ); ?>">
                                </div>
                        </div>

                        <div class="wca-settings-grid wca-settings">
                                <ul class="wca-vert-nav">
                                        <?php foreach ( $schema as $key => $group ) : ?>
                                                <li class="<?php echo $key === $tab ? 'active' : ''; ?>">
                                                        <a href="<?php echo esc_url( add_query_arg( array( 'section' => 'settings', 'tab' => $key ), $base ) ); ?>">
                                                                <?php echo esc_html( $group['title'] ); ?>
                                                        </a>
                                                </li>
                                        <?php endforeach; ?>
                                </ul>

                                <div class="wca-settings-form">
                                        <form method="post" action="options.php" class="wca-form">
                                                <?php settings_fields( 'wca_group_ext' ); ?>

                                                <?php if ( ! empty( $schema[ $tab ]['fields'] ) ) : ?>
                                                        <div class="wca-grid">
                                                                <?php
                                                                foreach ( $schema[ $tab ]['fields'] as $f ) :
                                                                        $id    = $f[0];
                                                                        $type  = $f[1];
                                                                        $label = $f[2];
                                                                        $def   = $f[3];
                                                                        $help  = $f[4] ?? '';
                                                                        $val   = isset( $opts[ $id ] ) ? $opts[ $id ] : $def;
                                                                        ?>
                                                                        <section class="wca-card wca-field" data-search="<?php echo esc_attr( strtolower( $label . ' ' . ( is_string( $help ) ? $help : implode( ' ', $help ) ) ) ); ?>">
                                                                                <header class="wca-card-h">
                                                                                        <h3><?php echo esc_html( $label ); ?></h3>
                                                                                        <?php if ( ! empty( $help ) ) : ?>
                                                                                                <button type="button" class="wca-help" aria-label="<?php esc_attr_e( 'Help', 'wc-anti-fraud-pro-lite' ); ?>">?</button>
                                                                                        <?php endif; ?>
                                                                                </header>
                                                                                <div class="wca-card-b">
                                                                                        <?php wca_render_input( $id, $type, $val, $help ); ?>
                                                                                </div>
                                                                        </section>
                                                                <?php endforeach; ?>
                                                        </div>
                                                <?php else : ?>
                                                        <p><?php esc_html_e( 'No configurable fields on this tab.', 'wc-anti-fraud-pro-lite' ); ?></p>
                                                <?php endif; ?>

                                                <div class="wca-savebar">
                                                        <?php submit_button( null, 'primary', 'submit', false ); ?>
                                                </div>
                                        </form>
                                </div>
                        </div>
               <?php elseif ( $section === 'logs' ) : ?>
                       <div class="wca-logs">
                               <?php wca_render_logs(); ?>
                       </div>
               <?php else : ?>
                       <div class="wca-dashboard">
                               <?php wca_render_dashboard(); ?>
                       </div>
               <?php endif; ?>
       </div>
       <?php
}

/* Render one input (compact, modern) */
function wca_render_input( $id, $type, $val, $help ) {
	echo '<div class="wca-input">';
	switch ( $type ) {
		case 'checkbox':
			echo '<label class="wca-switch"><input type="checkbox" name="wca_opts_ext[' . esc_attr( $id ) . ']" value="1" ' . checked( $val, 1, false ) . '><span class="wca-slider"></span></label>';
			break;

		case 'number_currency':
			$symbol = function_exists( 'get_woocommerce_currency_symbol' ) ? get_woocommerce_currency_symbol() : '';
			echo '<div class="wca-row">';
			echo '  <span class="wca-cur">' . $symbol . '</span>';
			echo '  <input type="number" step="0.01" min="0" inputmode="decimal" name="wca_opts_ext[' . esc_attr( $id ) . ']" value="' . esc_attr( is_numeric( $val ) ? $val : '0' ) . '" class="wca-num">';
			echo '</div>';
			break;

		case 'number':
			echo '<input type="number" min="0" inputmode="numeric" name="wca_opts_ext[' . esc_attr( $id ) . ']" value="' . esc_attr( $val ) . '" class="wca-num">';
			break;

		case 'textarea':
			echo '<textarea name="wca_opts_ext[' . esc_attr( $id ) . ']" rows="6" class="wca-ta">' . esc_textarea( $val ) . '</textarea>';
			break;

		case 'select':
			$choices = array();
			if ( $id === 'flag_match_mode' ) {
				$choices = array(
					'any' => __( 'Any (OR)', 'wc-anti-fraud-pro-lite' ),
					'all' => __( 'All (AND)', 'wc-anti-fraud-pro-lite' ),
				);
                        } else if ( $id === 'validation_profile' ) {
                                // Load validation presets and extract only the labels for the dropdown
                                $presets = wca_presets();
                                $choices = array( 'auto' => __( 'Automatic', 'wc-anti-fraud-pro-lite' ) );
                                foreach ( $presets as $index => $preset ) {
                                        $choices[ $index ] = $preset['label']; // use only label for option text
                                }
                        }
			echo '<select name="wca_opts_ext[' . esc_attr( $id ) . ']" class="wca-select">';
			foreach ( $choices as $k => $label ) {
				echo '<option value="' . esc_attr( $k ) . '" ' . selected( $val, $k, false ) . '>' . esc_html( $label ) . '</option>';
			}
			echo '</select>';
			break;

               case 'product_multi':
                       $selected = array();
                       $ids      = array_values( array_filter( array_map( 'intval', explode( ',', (string) $val ) ) ) );
                       if ( $ids ) {
                               $products = wc_get_products(
                                       array(
                                               'include' => $ids,
                                               'limit'   => count( $ids ),
                                       )
                               );
                               foreach ( $products as $product ) {
                                       $pid              = $product->get_id();
                                       $selected[ $pid ] = $product->get_name() . ' (#' . $pid . ')';
                               }
                       }
                       echo '<select multiple="multiple" id="wca-flag-products" name="wca_opts_ext[' . esc_attr( $id ) . '][]" class="wca-select2" style="width:100%">';
                       foreach ( $selected as $pid => $label ) {
                               echo '<option value="' . esc_attr( $pid ) . '" selected="selected">' . esc_html( $label ) . '</option>';
                       }
                       echo '</select>';
                       break;

		default:
			echo '<input type="text" name="wca_opts_ext[' . esc_attr( $id ) . ']" value="' . esc_attr( $val ) . '" class="regular-text wca-text">';
	}

	// inline help in a collapsible note
	if ( ! empty( $help ) ) {
		if ( is_array( $help ) ) {
			$help = implode( ' ', $help );
		}
		echo '<div class="wca-help-note" hidden>' . wp_kses_post( $help ) . '</div>';
	}
	echo '</div>';
}

/* Bans listing (best-effort) */
function wca_render_bans_table() {
	global $wpdb;
	$rows = $wpdb->get_results( "SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_wca_ban_%'" );
	echo '<table class="widefat striped"><thead><tr><th>' . esc_html__( 'IP (hashed key)', 'wc-anti-fraud-pro-lite' ) . '</th><th>' . esc_html__( 'Expires In', 'wc-anti-fraud-pro-lite' ) . '</th><th>' . esc_html__( 'Actions', 'wc-anti-fraud-pro-lite' ) . '</th></tr></thead><tbody>';
	if ( empty( $rows ) ) {
		echo '<tr><td colspan="3">' . esc_html__( 'No active bans.', 'wc-anti-fraud-pro-lite' ) . '</td></tr>';
	} else {
		foreach ( $rows as $r ) {
			// Compute remaining time using timeout twin
			$timeout = str_replace( '_transient_', '_transient_timeout_', $r->option_name );
			$exp_ts  = (int) get_option( $timeout, 0 );
			$remain  = $exp_ts ? max( 0, $exp_ts - time() ) : 0;
			$human   = $remain ? human_time_diff( time(), $exp_ts ) : __( 'unknown', 'wc-anti-fraud-pro-lite' );
			echo '<tr><td><code>' . esc_html( $r->option_name ) . '</code></td><td>' . esc_html( $human ) . '</td><td>';
			echo '<form method="post" style="display:inline">';
			wp_nonce_field( 'wca_tools_nonce', 'wca_tools_nonce' );
			echo '<input type="hidden" name="wca_tool" value="unban">';
			echo '<input type="hidden" name="wca_key" value="' . esc_attr( $r->option_name ) . '">';
			echo '<button class="button">' . esc_html__( 'Unban', 'wc-anti-fraud-pro-lite' ) . '</button>';
			echo '</form>';
			echo '</td></tr>';
		}
	}
	echo '</tbody></table>';
}

/* Handle maintenance tools */
function wca_handle_tools() {
	if ( ! is_admin() || ! current_user_can( 'manage_woocommerce' ) ) {
		return;
	}
	if ( ! isset( $_POST['wca_tool'] ) ) {
		return;
	}
	if ( ! isset( $_POST['wca_tools_nonce'] ) || ! wp_verify_nonce( $_POST['wca_tools_nonce'], 'wca_tools_nonce' ) ) {
		return;
	}

	global $wpdb;
	$tool = sanitize_text_field( $_POST['wca_tool'] );
        if ( $tool === 'clear-transients' ) {
                $keys = $wpdb->get_col( "SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE '_transient_wca_%' OR option_name LIKE '_transient_timeout_wca_%'" );
                foreach ( $keys as $k ) {
                        delete_option( $k );
                }
                wca_log_event( 'maintenance_clear', array(), 'info' );
                add_action( 'admin_notices', 'wca_notice_tools_success' );
        } elseif ( $tool === 'unban' ) {
                $key = isset( $_POST['wca_key'] ) ? sanitize_text_field( $_POST['wca_key'] ) : '';
                if ( $key ) {
                        if ( str_starts_with( $key, '_transient_wca_ban_' ) ) {
                                delete_option( $key );
                                $timeout = str_replace( '_transient_', '_transient_timeout_', $key );
                                delete_option( $timeout );
                                wca_log_event( 'ban_removed', array( 'key' => $key ), 'info' );
                                add_action( 'admin_notices', 'wca_notice_unban_success' );
                        } else {
                                add_action( 'admin_notices', 'wca_notice_unban_fail' );
                        }
                }
        }
}
function wca_notice_tools_success() {
	echo '<div class="notice notice-success"><p>' . esc_html__( 'Anti-fraud counters & bans cleared.', 'wc-anti-fraud-pro-lite' ) . '</p></div>';
}
function wca_notice_unban_success() {
        echo '<div class="notice notice-success"><p>' . esc_html__( 'Selected IP ban removed.', 'wc-anti-fraud-pro-lite' ) . '</p></div>';
}
function wca_notice_unban_fail() {
        echo '<div class="notice notice-warning"><p>' . esc_html__( 'Invalid ban key.', 'wc-anti-fraud-pro-lite' ) . '</p></div>';
}

function wca_notice_import_fail() {
        if ( get_transient( 'wca_import_error' ) ) {
                delete_transient( 'wca_import_error' );
                echo '<div class="notice notice-error"><p>' . esc_html__( 'Import failed: invalid file.', 'wc-anti-fraud-pro-lite' ) . '</p></div>';
        }
}
add_action( 'admin_notices', 'wca_notice_import_fail' );

/* Import/Export (unchanged) */
function wca_export_settings() {
	if ( ! current_user_can( 'manage_woocommerce' ) ) {
		wp_die( '' );
	}
	if ( ! isset( $_POST['wca_export_nonce'] ) || ! wp_verify_nonce( $_POST['wca_export_nonce'], 'wca_export_nonce' ) ) {
		wp_die( '' );
	}
	$opts = wca_opt();
	$json = wp_json_encode( $opts, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
	header( 'Content-Type: application/json' );
	header( 'Content-Disposition: attachment; filename="wca-settings.json"' );
	echo $json;
	exit;
}
function wca_import_settings() {
        if ( ! current_user_can( 'manage_woocommerce' ) ) {
                wp_die( '' );
        }
        if ( ! isset( $_POST['wca_import_nonce'] ) || ! wp_verify_nonce( $_POST['wca_import_nonce'], 'wca_import_nonce' ) ) {
                wp_die( '' );
        }
        if (
                ! isset( $_FILES['wca_file'] ) ||
                empty( $_FILES['wca_file']['tmp_name'] ) ||
                ( $_FILES['wca_file']['error'] ?? UPLOAD_ERR_NO_FILE ) !== UPLOAD_ERR_OK ||
                ( $_FILES['wca_file']['size'] ?? 0 ) > 1024 * 1024
        ) {
                set_transient( 'wca_import_error', 1, 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=' . WCA_MENU_SLUG ) );
                exit;
        }

        $file_check = wp_check_filetype_and_ext(
                $_FILES['wca_file']['tmp_name'],
                $_FILES['wca_file']['name'],
                array( 'json' => 'application/json' )
        );
        if ( ( $file_check['type'] ?? '' ) !== 'application/json' ) {
                set_transient( 'wca_import_error', 1, 30 );
                wp_safe_redirect( admin_url( 'admin.php?page=' . WCA_MENU_SLUG ) );
                exit;
        }

        $raw  = file_get_contents( $_FILES['wca_file']['tmp_name'] );
        $data = json_decode( $raw, true );
        if ( is_array( $data ) ) {
                $clean = wca_sanitize_opts_ext( $data );
                update_option( 'wca_opts_ext', $clean );
                wca_log_event( 'settings_imported', array( 'keys' => array_keys( $clean ) ), 'info' );
                delete_transient( 'wca_import_error' );
        } else {
                set_transient( 'wca_import_error', 1, 30 );
        }
        wp_safe_redirect( admin_url( 'admin.php?page=' . WCA_MENU_SLUG ) );
        exit;
}

/* Settings changed log */
function wca_on_settings_updated( $old, $new ) {
	$old     = is_array( $old ) ? $old : array();
	$new     = is_array( $new ) ? $new : array();
	$changed = array();
	foreach ( $new as $k => $v ) {
		if ( ! array_key_exists( $k, $old ) || $old[ $k ] !== $v ) {
			$changed[] = $k;
		}
	}
       if ( $changed ) {
               wca_log_event( 'settings_updated', array( 'keys' => $changed ), 'info' );
       }

       // Invalidate cached dashboard stats when settings change.
       delete_transient( 'wca_dash_stats' );
}