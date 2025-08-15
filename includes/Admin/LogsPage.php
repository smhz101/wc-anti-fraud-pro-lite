<?php
if (! defined('ABSPATH') ) {
        exit;
}

if (! class_exists('WP_List_Table') ) {
        include_once ABSPATH . 'wp-admin/includes/class-wp-list-table.php';
}

/**
 * Read and parse plugin log file lines.
 *
 * @return array[] Parsed entries with keys: time, level, event, context, raw, country, ip, items.
 */
function wca_get_log_entries()
{
    if (! function_exists('wc_get_log_file_path') ) {
            return array();
    }
        $path = wc_get_log_file_path('wc-antifraud-pro-lite');
    if (! file_exists($path) ) {
            return array();
    }
       $limit = 5000; // Only read the last 5,000 lines to limit memory usage; adjust as needed.
       $file  = new SplFileObject($path, 'r');
       $file->seek(PHP_INT_MAX);
       $last_line = $file->key();
       $start     = max(0, $last_line - $limit);
       $file->seek($start);
       $out = array();
   while (! $file->eof() ) {
           $line = rtrim($file->fgets(), "\r\n");
       if ($line === '') {
               continue;
       }
           $json_start = strpos($line, '{');
       if ($json_start === false ) {
               continue;
       }
           $json = substr($line, $json_start);
           $data = json_decode($json, true);
       if (! is_array($data) ) {
               continue;
       }
           preg_match('/\b(INFO|DEBUG|WARNING|ERROR)\b/i', $line, $m);
           $level   = strtolower($m[1] ?? 'info');
           $time    = isset($data['time']) ? (int) $data['time'] : time();
           $event   = isset($data['event']) ? (string) $data['event'] : '';
           $context = $data;
           unset($context['event'], $context['time']);
           $out[] = array(
                   'time'    => $time,
                   'level'   => $level,
                   'event'   => $event,
                   'context' => wp_json_encode($context),
                   'raw'     => $line,
                   'country' => $data['country'] ?? '',
                   'ip'      => $data['ip'] ?? '',
                   'items'   => $data['items'] ?? array(),
           );
   }
       return $out;
}

/**
 * WP_List_Table implementation for plugin logs.
 */
class WCA_Log_Table extends WP_List_Table
{
    public function get_columns()
    {
            return array(
                    'time'    => __('Time', 'wc-anti-fraud-pro-lite'),
                    'level'   => __('Level', 'wc-anti-fraud-pro-lite'),
                    'event'   => __('Event', 'wc-anti-fraud-pro-lite'),
                    'context' => __('Context', 'wc-anti-fraud-pro-lite'),
            );
    }

    protected function get_sortable_columns()
    {
            return array(
                    'time'  => array( 'time', true ),
                    'level' => array( 'level', false ),
                    'event' => array( 'event', false ),
            );
    }

    public function column_default( $item, $column_name )
    {
            switch ( $column_name ) {
        case 'time':
            return esc_html(date_i18n(get_option('date_format') . ' ' . get_option('time_format'), (int) $item['time']));
        case 'level':
            return esc_html(strtoupper($item['level']));
        case 'event':
            return esc_html($item['event']);
        case 'context':
            return '<code>' . esc_html($item['context']) . '</code>';
        default:
            return '';
            }
    }

    public function prepare_items()
    {
            $entries = wca_get_log_entries();

            $search = isset($_REQUEST['s']) ? strtolower(sanitize_text_field($_REQUEST['s'])) : '';
            $level  = isset($_REQUEST['level']) ? strtolower(sanitize_key($_REQUEST['level'])) : '';
            $event  = isset($_REQUEST['event']) ? strtolower(sanitize_key($_REQUEST['event'])) : '';

            $filtered = array();
        foreach ( $entries as $e ) {
            if ($level && strtolower($e['level']) !== $level ) {
                continue;
            }
            if ($event && strtolower($e['event']) !== $event ) {
                    continue;
            }
            if ($search && strpos(strtolower($e['raw']), $search) === false ) {
                    continue;
            }
                $filtered[] = $e;
        }

            $orderby = isset($_REQUEST['orderby']) ? sanitize_key($_REQUEST['orderby']) : 'time';
            $order   = isset($_REQUEST['order']) && 'asc' === strtolower($_REQUEST['order']) ? 'asc' : 'desc';
            usort(
                $filtered,
                function ( $a, $b ) use ( $orderby, $order ) {
                        $v1 = $a[ $orderby ] ?? '';
                        $v2 = $b[ $orderby ] ?? '';
                    if ($v1 == $v2 ) {
                            return 0;
                    }
                    if ('asc' === $order ) {
                            return ( $v1 < $v2 ) ? -1 : 1;
                    }
                        return ( $v1 > $v2 ) ? -1 : 1;
                }
            );

            $per_page     = 20;
            $current_page = $this->get_pagenum();
            $total_items  = count($filtered);

            $this->items = array_slice($filtered, ( $current_page - 1 ) * $per_page, $per_page);

            $this->set_pagination_args(
                array(
                            'total_items' => $total_items,
                            'per_page'    => $per_page,
                            'total_pages' => ceil($total_items / $per_page),
                    )
            );
    }
}

/**
 * Render logs page.
 */
function wca_render_logs()
{
        $table = new WCA_Log_Table();
        $table->prepare_items();
        $level  = isset($_GET['level']) ? sanitize_key($_GET['level']) : '';
        $event  = isset($_GET['event']) ? sanitize_key($_GET['event']) : '';
        $action = esc_url(menu_page_url(WCA_MENU_SLUG, false));
    ?>
        <form method="get" action="<?php echo $action; ?>" id="wca-log-filters">
                <input type="hidden" name="page" value="<?php echo esc_attr(WCA_MENU_SLUG); ?>" />
                <input type="hidden" name="section" value="logs" />
                <div class="wca-log-filters">
                        <?php $table->search_box(__('Search logs', 'wc-anti-fraud-pro-lite'), 'wca-log-search'); ?>
                        <select name="level">
                                <option value=""><?php esc_html_e('All levels', 'wc-anti-fraud-pro-lite'); ?></option>
                                <option value="info" <?php selected($level, 'info'); ?>>INFO</option>
                                <option value="warning" <?php selected($level, 'warning'); ?>>WARNING</option>
                                <option value="error" <?php selected($level, 'error'); ?>>ERROR</option>
                        </select>
                        <select name="event">
                                <option value=""><?php esc_html_e('All events', 'wc-anti-fraud-pro-lite'); ?></option>
                                <option value="pass" <?php selected($event, 'pass'); ?>>pass</option>
                                <option value="blocked" <?php selected($event, 'blocked'); ?>>blocked</option>
                        </select>
                        <noscript><button class="button">Filter</button></noscript>
                </div>
                <div id="wca-log-table">
                        <?php $table->display(); ?>
                </div>
        </form>
        <?php
}
