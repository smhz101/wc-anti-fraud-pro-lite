<?php
if ( ! defined( 'ABSPATH' ) ) {
        exit;
}

/**
 * Render dashboard with basic analytics.
 */
function wca_render_dashboard() {
        $cached    = get_transient( 'wca_dash_stats' );
        $log_file  = function_exists( 'wc_get_log_file_path' ) ? wc_get_log_file_path( 'wc-antifraud-pro-lite' ) : '';
        $log_mtime = ( $log_file && file_exists( $log_file ) ) ? filemtime( $log_file ) : 0;
        $opts_hash = md5( wp_json_encode( get_option( 'wca_opts_ext', array() ) ) );

        if ( $cached && isset( $cached['log_mtime'], $cached['opts_hash'] ) &&
                (int) $cached['log_mtime'] === $log_mtime && $cached['opts_hash'] === $opts_hash ) {
                $stats     = $cached['stats'];
                $countries = $cached['countries'];
                $products  = $cached['products'];
                $ips       = $cached['ips'];
        } else {
                $entries  = function_exists( 'wca_get_log_entries' ) ? wca_get_log_entries() : array();
                $now      = time();
                $day_ago  = $now - DAY_IN_SECONDS;
                $week_ago = $now - WEEK_IN_SECONDS;

                $stats     = array(
                        'day'  => array( 'pass' => 0, 'blocked' => 0 ),
                        'week' => array( 'pass' => 0, 'blocked' => 0 ),
                );
                $countries = array();
                $products  = array();
                $ips       = array();

                foreach ( $entries as $e ) {
                        $time  = (int) $e['time'];
                        $event = $e['event'];
                        if ( $event === 'pass' || $event === 'blocked' ) {
                                if ( $time >= $day_ago ) {
                                        $stats['day'][ $event ]++;
                                }
                                if ( $time >= $week_ago ) {
                                        $stats['week'][ $event ]++;
                                }
                        }
                        if ( $event === 'blocked' ) {
                                if ( ! empty( $e['country'] ) ) {
                                        $countries[ $e['country'] ] = isset( $countries[ $e['country'] ] ) ? $countries[ $e['country'] ] + 1 : 1;
                                }
                                if ( ! empty( $e['ip'] ) ) {
                                        $ips[ $e['ip'] ] = isset( $ips[ $e['ip'] ] ) ? $ips[ $e['ip'] ] + 1 : 1;
                                }
                                if ( ! empty( $e['items'] ) && is_array( $e['items'] ) ) {
                                        foreach ( $e['items'] as $it ) {
                                                $pid = $it['pid'] ?? 0;
                                                if ( $pid ) {
                                                        $products[ $pid ] = isset( $products[ $pid ] ) ? $products[ $pid ] + 1 : 1;
                                                }
                                        }
                                }
                        }
                }

                arsort( $countries );
                arsort( $products );
                arsort( $ips );
                $countries = array_slice( $countries, 0, 5, true );
                $products  = array_slice( $products, 0, 5, true );
                $ips       = array_slice( $ips, 0, 5, true );

                set_transient(
                        'wca_dash_stats',
                        array(
                                'stats'     => $stats,
                                'countries' => $countries,
                                'products'  => $products,
                                'ips'       => $ips,
                                'log_mtime' => $log_mtime,
                                'opts_hash' => $opts_hash,
                        ),
                        5 * MINUTE_IN_SECONDS
                );
        }

        $bans = function_exists( 'wca_list_bans' ) ? wca_list_bans() : array();
        ?>
        <div class="wca-grid">
                <section class="wca-card">
                        <header class="wca-card-h"><h3><?php esc_html_e( 'Checkouts', 'wc-anti-fraud-pro-lite' ); ?></h3></header>
                        <div class="wca-card-b">
                                <div class="wca-chart">
                                       <canvas id="wca-checkout-chart" data-stats='<?php echo esc_attr( wp_json_encode( $stats ) ); ?>'></canvas>
                                </div>
                        </div>
                </section>
               <section class="wca-card">
                       <header class="wca-card-h"><h3><?php esc_html_e( 'Active bans', 'wc-anti-fraud-pro-lite' ); ?></h3></header>
                       <div class="wca-card-b">
                               <p><strong><?php echo esc_html( count( $bans ) ); ?></strong></p>
                               <?php if ( $bans ) : ?>
                                       <ul>
                                               <?php foreach ( array_slice( $bans, 0, 5 ) as $b ) : ?>
                                                       <li><?php echo esc_html( $b['hash'] . ' (' . human_time_diff( time(), time() + $b['seconds_left'] ) . ')' ); ?></li>
                                               <?php endforeach; ?>
                                       </ul>
                               <?php else : ?>
                                       <p><?php esc_html_e( 'None', 'wc-anti-fraud-pro-lite' ); ?></p>
                               <?php endif; ?>
                       </div>
               </section>
                <section class="wca-card">
                        <header class="wca-card-h"><h3><?php esc_html_e( 'Top blocked countries', 'wc-anti-fraud-pro-lite' ); ?></h3></header>
                        <div class="wca-card-b">
                                <?php if ( $countries ) : ?>
                                        <ul>
                                                <?php foreach ( $countries as $k => $v ) : ?>
                                                        <li><?php echo esc_html( $k . ' – ' . $v ); ?></li>
                                                <?php endforeach; ?>
                                        </ul>
                                <?php else : ?>
                                        <p><?php esc_html_e( 'No data', 'wc-anti-fraud-pro-lite' ); ?></p>
                                <?php endif; ?>
                        </div>
                </section>
                <section class="wca-card">
                        <header class="wca-card-h"><h3><?php esc_html_e( 'Top blocked products', 'wc-anti-fraud-pro-lite' ); ?></h3></header>
                        <div class="wca-card-b">
                                <?php if ( $products ) : ?>
                                        <ul>
                                                <?php foreach ( $products as $pid => $cnt ) : ?>
                                                        <li><?php echo esc_html( get_the_title( $pid ) . ' (#' . $pid . ') – ' . $cnt ); ?></li>
                                                <?php endforeach; ?>
                                        </ul>
                                <?php else : ?>
                                        <p><?php esc_html_e( 'No data', 'wc-anti-fraud-pro-lite' ); ?></p>
                                <?php endif; ?>
                        </div>
                </section>
                <section class="wca-card">
                        <header class="wca-card-h"><h3><?php esc_html_e( 'Top blocked IPs', 'wc-anti-fraud-pro-lite' ); ?></h3></header>
                        <div class="wca-card-b">
                                <?php if ( $ips ) : ?>
                                        <ul>
                                                <?php foreach ( $ips as $ip => $cnt ) : ?>
                                                        <li><?php echo esc_html( $ip . ' – ' . $cnt ); ?></li>
                                                <?php endforeach; ?>
                                        </ul>
                                <?php else : ?>
                                        <p><?php esc_html_e( 'No data', 'wc-anti-fraud-pro-lite' ); ?></p>
                                <?php endif; ?>
                        </div>
                </section>
        </div>
        <?php
}
