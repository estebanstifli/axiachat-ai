<?php
if ( ! defined('ABSPATH') ) { exit; }

function aichat_logs_page() {
    if ( ! current_user_can('manage_options') ) {
        wp_die( esc_html__('Unauthorized','aichat') );
    }

    $logging_enabled = (bool) get_option('aichat_logging_enabled', 1);

    global $wpdb;
    $table = $wpdb->prefix.'aichat_conversations';

    // Filtros con validación de nonce (graceful si falta)
    $nonce_valid = true;
    if ( isset($_GET['aichat_logs_nonce']) ) {
        $nonce_raw = sanitize_text_field( wp_unslash( $_GET['aichat_logs_nonce'] ) );
        if ( ! wp_verify_nonce( $nonce_raw, 'aichat_logs_filter' ) ) {
            $nonce_valid = false;
        }
    }
    if ( ! $nonce_valid ) {
        echo '<div class="wrap"><h1>'.esc_html__('Conversation Logs','aichat').'</h1><div class="notice notice-error"><p>'.esc_html__('Security check failed. Reload the page and try again.','aichat').'</p></div></div>';
        return;
    }

    $date_from = isset($_GET['date_from']) ? sanitize_text_field($_GET['date_from']) : '';
    $date_to   = isset($_GET['date_to'])   ? sanitize_text_field($_GET['date_to'])   : '';
    $user_id   = isset($_GET['user_id'])   ? absint($_GET['user_id']) : 0;
    $bot_slug  = isset($_GET['bot_slug'])  ? sanitize_title($_GET['bot_slug']) : '';

    $where = [];
    $params = [];

    if ( $date_from && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_from) ) {
        $where[] = 'c.created_at >= %s';
        $params[] = $date_from . ' 00:00:00';
    }
    if ( $date_to && preg_match('/^\d{4}-\d{2}-\d{2}$/',$date_to) ) {
        $where[] = 'c.created_at <= %s';
        $params[] = $date_to . ' 23:59:59';
    }
    if ( $user_id > 0 ) {
        $where[] = 'c.user_id = %d';
        $params[] = $user_id;
    }
    if ( $bot_slug ) {
        $where[] = 'c.bot_slug = %s';
        $params[] = $bot_slug;
    }

    $where_sql = $where ? ('WHERE '.implode(' AND ',$where)) : '';

    // Paginación
    $per_page = 50;
    $paged = max(1, isset($_GET['paged']) ? absint($_GET['paged']) : 1);
    $offset = ($paged-1)*$per_page;

    // Total de sesiones (session_id + bot_slug)
    $sql_count = "
        SELECT COUNT(*) FROM (
            SELECT session_id, bot_slug
            FROM $table c
            $where_sql
            GROUP BY session_id, bot_slug
        ) t
    ";
    // Evitar llamar a prepare sin placeholders
    if ( $params ) {
        $total = (int) $wpdb->get_var( $wpdb->prepare( $sql_count, $params ) );
    } else {
        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Static query without user-supplied input (no filters); safe.
        $total = (int) $wpdb->get_var( $sql_count );
    }

    // Listado principal
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- $where_sql is built only from whitelisted fragments with placeholders; values bound via $wpdb->prepare below.
    $sql = "
        SELECT 
            MIN(c.created_at) AS first_at,
            MAX(c.created_at) AS last_at,
            c.session_id,
            c.bot_slug,
            COUNT(*) AS messages,
            MAX(c.id) AS last_id,
            SUM( CASE WHEN c.user_id>0 THEN 1 ELSE 0 END ) AS has_user,
            MAX(c.user_id) AS any_user
        FROM $table c
        $where_sql
        GROUP BY c.session_id, c.bot_slug
        ORDER BY last_at DESC
        LIMIT %d OFFSET %d
    ";
    $q_params = $params;
    $q_params[] = $per_page;
    $q_params[] = $offset;

    // Preparar consulta listado
    if ( $params ) {
        $rows = $wpdb->get_results( $wpdb->prepare($sql, $q_params), ARRAY_A );
    } else {
        // Cuando no hay filtros dinámicos, sólo per_page y offset son variables
        $rows = $wpdb->get_results( $wpdb->prepare($sql, [ $per_page, $offset ] ), ARRAY_A );
    }

    // Obtener último mensaje corto
    $last_map = [];
    if ( $rows ) {
        $last_ids = wp_list_pluck($rows,'last_id');
        $placeholders = implode(',', array_fill(0, count($last_ids), '%d'));
    // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- IN clause placeholders (%d) generated to match sanitized integer IDs; executed with $wpdb->prepare.
    $sql_last = "SELECT id, response, message FROM $table WHERE id IN ($placeholders)";
        $last_res = $wpdb->get_results( $wpdb->prepare($sql_last, $last_ids), ARRAY_A );
        foreach($last_res as $r){
            $txt = trim( wp_strip_all_tags( $r['response'] ?: $r['message'] ) );
            if ( mb_strlen($txt) > 120 ) {
                $txt = mb_substr($txt,0,120).'…';
            }
            $last_map[ $r['id'] ] = $txt;
        }
    }

    // Bots para filtro
    $bots = $wpdb->get_col("SELECT DISTINCT bot_slug FROM $table ORDER BY bot_slug ASC");

    // Usuarios (opcional: sólo los que aparecen)
    $users = $wpdb->get_col("SELECT DISTINCT user_id FROM $table WHERE user_id>0 ORDER BY user_id ASC");
    ?>
    <div class="wrap">
        <h1 class="mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-chat-dots"></i> <?php echo esc_html__('Conversation Logs','aichat'); ?>
        </h1>

        <?php if ( ! $logging_enabled ) : ?>
            <div class="notice notice-warning">
                <p><?php echo esc_html__('Conversation logging is currently disabled. No new entries are being stored.', 'aichat'); ?></p>
            </div>
        <?php endif; ?>

        <?php if ( isset($_GET['deleted']) ) : ?>
            <div class="notice notice-success is-dismissible"><p><?php echo esc_html__('Conversation deleted.', 'aichat'); ?></p></div>
        <?php endif; ?>

        <style>
            /* Ajustes de layout para filtros de logs */
            .aichat-logs-filters.card { width:100%; box-sizing:border-box; }
            @media (min-width: 768px){
                .aichat-logs-filters .form-label { font-weight:600; }
            }
        </style>
        <form method="get" class="aichat-logs-filters card card-body mb-4 shadow-sm">
            <input type="hidden" name="page" value="aichat-logs" />
            <?php wp_nonce_field('aichat_logs_filter','aichat_logs_nonce'); ?>
            <div class="row g-3 align-items-end">
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php esc_html_e('Date From','aichat'); ?></label>
                    <input type="date" name="date_from" value="<?php echo esc_attr($date_from); ?>" class="form-control" />
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php esc_html_e('Date To','aichat'); ?></label>
                    <input type="date" name="date_to" value="<?php echo esc_attr($date_to); ?>" class="form-control" />
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php esc_html_e('User ID','aichat'); ?></label>
                    <select name="user_id" class="form-select">
                        <option value="0">— <?php esc_html_e('All','aichat'); ?> —</option>
                        <?php foreach($users as $u): ?>
                            <option value="<?php echo (int)$u; ?>" <?php selected($user_id,$u); ?>><?php echo (int)$u; ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-2 col-sm-6">
                    <label class="form-label"><?php esc_html_e('Bot','aichat'); ?></label>
                    <select name="bot_slug" class="form-select">
                        <option value="">— <?php esc_html_e('All','aichat'); ?> —</option>
                        <?php foreach($bots as $b): ?>
                            <option value="<?php echo esc_attr($b); ?>" <?php selected($bot_slug,$b); ?>><?php echo esc_html($b); ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4 col-sm-12 d-flex flex-wrap gap-2">
                    <button type="submit" class="button button-primary">
                        <i class="bi bi-funnel"></i> <?php esc_html_e('Filter','aichat'); ?>
                    </button>
                    <a href="<?php echo esc_url( admin_url('admin.php?page=aichat-logs') ); ?>" class="button button-secondary">
                        <i class="bi bi-x-circle"></i> <?php esc_html_e('Reset','aichat'); ?>
                    </a>
                </div>
            </div>
        </form>

        <div class="table-responsive">
            <table class="table table-hover align-middle">
                <thead class="table-light">
                    <tr>
                        <th><?php esc_html_e('Date','aichat'); ?></th>
                        <th><?php esc_html_e('User','aichat'); ?></th>
                        <th><?php esc_html_e('Bot','aichat'); ?></th>
                        <th><?php esc_html_e('Messages','aichat'); ?></th>
                        <th><?php esc_html_e('Last Snippet','aichat'); ?></th>
                        <th><?php esc_html_e('Detail','aichat'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $rows ) : ?>
                    <?php foreach( $rows as $r ): 
                        $snippet = isset($last_map[$r['last_id']]) ? $last_map[$r['last_id']] : '';
                        $detail_url = add_query_arg([
                            'page'=>'aichat-logs-detail',
                            'session'=>rawurlencode($r['session_id']),
                            'bot'=>rawurlencode($r['bot_slug'])
                        ], admin_url('admin.php'));
                    ?>
                        <tr>
                            <td>
                                <div class="small text-muted"><?php echo esc_html( $r['first_at'] ); ?></div>
                                <div class="fw-semibold"><?php echo esc_html( $r['last_at'] ); ?></div>
                            </td>
                            <td><?php echo $r['any_user'] ? intval($r['any_user']) : esc_html('—'); ?></td>
                            <td><code><?php echo esc_html($r['bot_slug']); ?></code></td>
                            <td><?php echo (int)$r['messages']; ?></td>
                            <td class="small"><?php echo esc_html($snippet); ?></td>
                            <td>
                                <a class="button button-small" href="<?php echo esc_url($detail_url); ?>">
                                    <i class="bi bi-box-arrow-in-right"></i> <?php esc_html_e('Open','aichat'); ?>
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="6" class="text-center text-muted py-4">
                        <i class="bi bi-info-circle"></i> <?php esc_html_e('No conversations found.','aichat'); ?>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php
        $total_pages = max(1, (int)ceil($total / $per_page));
        if ( $total_pages > 1 ):
            $base_url = remove_query_arg('paged');
            ?>
            <nav>
                <ul class="pagination">
                    <?php for($p=1;$p<=$total_pages;$p++): 
                        $u = add_query_arg('paged',$p,$base_url);
                        ?>
                        <li class="page-item <?php echo esc_attr($p===$paged?'active':''); ?>">
                            <a class="page-link" href="<?php echo esc_url($u); ?>"><?php echo (int)$p; ?></a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
    <?php
}