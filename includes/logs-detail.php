<?php
if ( ! defined('ABSPATH') ) { exit; }

function aichat_logs_detail_page() {
    if ( ! current_user_can('manage_options') ) {
    wp_die( esc_html__('Unauthorized','ai-chat') );
    }
    global $wpdb;
    $table = $wpdb->prefix.'aichat_conversations';

    $session = isset($_GET['session']) ? preg_replace('/[^a-z0-9\-]/i','', $_GET['session']) : '';
    $bot     = isset($_GET['bot']) ? sanitize_title($_GET['bot']) : '';

    if ( ! $session || ! $bot ) {
    echo '<div class="wrap"><h1>'.esc_html__('Conversation Detail','ai-chat').'</h1><p>'.esc_html__('Missing parameters.','ai-chat').'</p></div>';
        return;
    }

    $rows = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT id, user_id, message, response, created_at 
             FROM $table
             WHERE session_id=%s AND bot_slug=%s
             ORDER BY id ASC",
            $session, $bot
        ),
        ARRAY_A
    );

    $user_id = 0;
    if ( $rows ) {
        foreach($rows as $r){ if ( (int)$r['user_id'] > 0 ) { $user_id = (int)$r['user_id']; break; } }
    }

    $total = $rows ? count($rows) : 0;

    // Preparar pares (alternando user/bot)
    $messages = [];
    if ( $rows ) {
        foreach ( $rows as $r ) {
            if ( $r['message'] !== '' ) {
                $messages[] = [
                    'role' => 'user',
                    'text' => $r['message'],
                    'at'   => $r['created_at']
                ];
            }
            if ( $r['response'] !== '' ) {
                $messages[] = [
                    'role' => 'assistant',
                    'text' => $r['response'],
                    'at'   => $r['created_at']
                ];
            }
        }
    }

    // URL de retorno (se escapa en el punto de salida para satisfacer sniff de seguridad)
    $back_url = admin_url('admin.php?page=aichat-logs');
    ?>
    <div class="wrap">
        <h1 class="mb-3 d-flex align-items-center gap-2">
            <i class="bi bi-chat-square-text"></i> <?php esc_html_e('Conversation Detail','ai-chat'); ?>
        </h1>

        <div class="card mb-4 shadow-sm">
            <div class="card-body row g-4">
                <div class="col-md-3">
                    <div class="small text-muted"><?php esc_html_e('Session ID','ai-chat'); ?></div>
                    <code><?php echo esc_html($session); ?></code>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted"><?php esc_html_e('Bot','ai-chat'); ?></div>
                    <code><?php echo esc_html($bot); ?></code>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted"><?php esc_html_e('User ID','ai-chat'); ?></div>
                    <span><?php echo $user_id ? intval($user_id) : '—'; ?></span>
                </div>
                <div class="col-md-2">
                    <div class="small text-muted"><?php esc_html_e('Messages','ai-chat'); ?></div>
                    <span class="fw-semibold"><?php echo (int)$total; ?></span>
                </div>
                <div class="col-md-3 d-flex align-items-start gap-2 justify-content-end">
                    <a href="<?php echo esc_url( $back_url ); ?>" class="button">
                        <i class="bi bi-arrow-left-circle"></i> <?php esc_html_e('Back','ai-chat'); ?>
                    </a>
                    <form method="post" action="<?php echo esc_url( admin_url('admin-post.php') ); ?>" onsubmit="return confirm('<?php echo esc_attr__('Delete this entire conversation?','ai-chat'); ?>');">
                        <?php wp_nonce_field('aichat_delete_conversation'); ?>
                        <input type="hidden" name="action" value="aichat_delete_conversation" />
                        <input type="hidden" name="session_id" value="<?php echo esc_attr($session); ?>" />
                        <input type="hidden" name="bot_slug" value="<?php echo esc_attr($bot); ?>" />
                        <button type="submit" class="button button-danger">
                            <i class="bi bi-trash"></i> <?php esc_html_e('Delete','ai-chat'); ?>
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <div class="table-responsive">
            <table class="table table-striped align-middle">
                <thead class="table-light">
                    <tr>
                        <th style="width:140px;"><?php esc_html_e('Time','ai-chat'); ?></th>
                        <th style="width:110px;"><?php esc_html_e('Role','ai-chat'); ?></th>
                        <th><?php esc_html_e('Text','ai-chat'); ?></th>
                    </tr>
                </thead>
                <tbody>
                <?php if ( $messages ) : ?>
                    <?php foreach( $messages as $m ): 
                        $role_label = $m['role']==='user' ? __('User','ai-chat') : __('Assistant','ai-chat');
                        ?>
                        <tr>
                            <td class="small text-muted"><?php echo esc_html($m['at']); ?></td>
                            <td>
                                <span class="badge bg-<?php echo esc_attr( $m['role']==='user' ? 'primary' : 'secondary' ); ?>">
                                    <?php echo esc_html($role_label); ?>
                                </span>
                            </td>
                            <td class="small">
                                <?php
                                // Mostrar HTML seguro de la respuesta; mensajes user se escapan
                                if ( $m['role'] === 'assistant' ) {
                                    echo wp_kses_post( $m['text'] );
                                } else {
                                    echo esc_html( wp_strip_all_tags( $m['text'] ) );
                                }
                                ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr><td colspan="3" class="text-center text-muted py-4">
                        <i class="bi bi-info-circle"></i> <?php esc_html_e('No messages in this conversation.','ai-chat'); ?>
                    </td></tr>
                <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
}