<?php
/**
 * AI Chat — AJAX (con trazas)
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Definir bandera de depuración si no existe para evitar notices
if ( ! defined( 'AICHAT_DEBUG') ) {
    define( 'AICHAT_DEBUG', false );
}

if ( ! class_exists( 'AIChat_Ajax' ) ) {

    class AIChat_Ajax {
        private static $instance = null;

        public static function instance() {
            if ( self::$instance === null ) {
                self::$instance = new self();
            }
            return self::$instance;
        }

        private function __construct() {
            add_action( 'wp_ajax_aichat_process_message', [ $this, 'process_message' ] );
            add_action( 'wp_ajax_nopriv_aichat_process_message', [ $this, 'process_message' ] );
            add_action( 'wp_ajax_aichat_get_history', [ $this, 'get_history' ] );
            add_action( 'wp_ajax_nopriv_aichat_get_history', [ $this, 'get_history' ] );
        }

        public function process_message() {
            $t0 = microtime(true);
            $uid = wp_generate_uuid4();

            // --- Flag de depuración ---
            $debug = false;
            if ( defined('AICHAT_DEBUG') && AICHAT_DEBUG ) { $debug = true; }
            if ( isset($_POST['debug']) && $_POST['debug'] ) { $debug = true; }

            aichat_log_debug("[AIChat AJAX][$uid] start");

            // Nonce
            $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
            if ( empty($nonce) || ! wp_verify_nonce( $nonce, 'aichat_ajax' ) ) {
                aichat_log_debug("[AIChat AJAX][$uid] nonce invalid");
                wp_send_json_error( [ 'message' => __( 'Invalid nonce.', 'ai-chat' ) ], 403 );
            }

            $message  = isset( $_POST['message'] )  ? sanitize_textarea_field( wp_unslash( $_POST['message'] ) ) : '';
            $bot_slug = isset( $_POST['bot_slug'] ) ? sanitize_title( wp_unslash( $_POST['bot_slug'] ) ) : '';
            $session  = isset( $_POST['session_id'] ) ? preg_replace('/[^a-z0-9\-]/i','', (string)wp_unslash($_POST['session_id'])) : '';
            if ($session==='') { $session = wp_generate_uuid4(); }

            if ( $message === '' ) {
                aichat_log_debug("[AIChat AJAX][$uid] empty message");
                wp_send_json_error( [ 'message' => __( 'Message is empty.', 'ai-chat' ) ], 400 );
            }
            aichat_log_debug("[AIChat AJAX][$uid] payload slug={$bot_slug} msg_len=" . strlen($message));

            // ---- CAPTCHA (filtro opcional) ----
            $captcha_ok = apply_filters( 'aichat_validate_captcha', true, $_POST );
            if ( ! $captcha_ok ) {
                aichat_log_debug("[AIChat AJAX][$uid] captcha failed");
                wp_send_json_error( [ 'message' => __( 'Captcha validation failed.', 'ai-chat' ) ], 403 );
            }

            // ---- Honeypot anti bots ----
            if ( ! empty( $_POST['aichat_hp'] ) ) {
                aichat_log_debug("[AIChat AJAX][$uid] honeypot filled");
                wp_send_json_error( [ 'message' => __( 'Request blocked.', 'ai-chat' ) ], 403 );
            }

            // ---- Rate limiting & burst control ----
            if ( function_exists('aichat_rate_limit_check') ) {
                $rl = aichat_rate_limit_check( $session, $bot_slug );
                if ( is_wp_error( $rl ) ) {
                    aichat_log_debug("[AIChat AJAX][$uid] rate limit: " . $rl->get_error_code());
                    wp_send_json_error( [ 'message' => $rl->get_error_message() ], 429 );
                }
            }

            // ---- Longitud máxima dura (defensa DoS semántico) ----
            $len = mb_strlen( $message );
            if ( $len > 4000 ) {
                aichat_log_debug("[AIChat AJAX][$uid] message too long len=$len");
                wp_send_json_error( [ 'message' => __( 'Message too long.', 'ai-chat' ) ], 400 );
            }

            // ---- Firma / Patrón de spam básico ----
            if ( function_exists('aichat_spam_signature_check') ) {
                $sig = aichat_spam_signature_check( $message );
                if ( is_wp_error( $sig ) && $sig->get_error_code() !== 'aichat_empty' ) {
                    aichat_log_debug("[AIChat AJAX][$uid] spam signature: " . $sig->get_error_code());
                    wp_send_json_error( [ 'message' => __( 'Blocked.', 'ai-chat' ) ], 400 );
                }
            }

            // ---- Moderación temprana ----
            if ( function_exists('aichat_run_moderation_checks') ) {
                $mod_check = aichat_run_moderation_checks( $message );
                if ( is_wp_error( $mod_check ) ) {
                    $rej_msg = $mod_check->get_error_message();
                    aichat_log_debug("[AIChat AJAX][$uid] moderation blocked: " . $rej_msg);
                    if ( function_exists('aichat_record_moderation_block') ) {
                        aichat_record_moderation_block( $mod_check->get_error_code() );
                    }

                    // Opcional: NO llamamos a proveedor, devolvemos mensaje como respuesta del bot
                    // Si quisieras registrar la interacción en la tabla, descomenta:
                    // $this->maybe_log_conversation( get_current_user_id(), $session, $bot_slug ?: 'moderation', 0, $message, $rej_msg );

                    wp_send_json_success( [
                        'message'    => $rej_msg,
                        'moderated'  => true
                    ] );
                }
            }
            // ---- Fin moderación ----

            // 1) Resolver BOT
            $bot = $this->resolve_bot( $bot_slug );
            if ( ! $bot ) {
                aichat_log_debug("[AIChat AJAX][$uid] resolve_bot: NOT FOUND");
                wp_send_json_error( [ 'message' => __( 'No bot found to process the request.', 'ai-chat' ) ], 404 );
            }

            // Sanitiza/normaliza campos del bot
            $bot_id       = isset($bot['id'])   ? intval($bot['id']) : 0;
            $bot_name     = isset($bot['name']) ? sanitize_text_field($bot['name']) : '';
            $bot_slug_r   = isset($bot['slug']) ? sanitize_title($bot['slug']) : $bot_slug;

            $provider     = ! empty( $bot['provider'] ) ? sanitize_key( $bot['provider'] ) : 'openai'; // 'openai' | 'claude'
            $model        = ! empty( $bot['model'] ) ? sanitize_text_field( $bot['model'] ) : ( $provider === 'claude' ? 'claude-3-haiku-20240307' : 'gpt-4o-mini' );
            $instructions = isset( $bot['instructions'] ) ? wp_kses_post( $bot['instructions'] ) : '';
            $temperature  = isset( $bot['temperature'] ) ? floatval( $bot['temperature'] ) : 0.7;
            if ( $temperature < 0 ) $temperature = 0;
            if ( $temperature > 2 ) $temperature = 2;
            $max_tokens   = isset( $bot['max_tokens'] ) ? intval( $bot['max_tokens'] ) : 512;
            if ( $max_tokens <= 0 ) $max_tokens = 512;

            $context_mode = isset( $bot['context_mode'] ) ? sanitize_key( $bot['context_mode'] ) : 'auto'; // 'embeddings'|'none'|'page' (legacy)
            $context_id   = isset( $bot['context_id'] )   ? intval( $bot['context_id'] ) : 0;

            aichat_log_debug("[AIChat AJAX][$uid] bot id={$bot_id} slug={$bot_slug_r} name={$bot_name} provider={$provider} model={$model} temp={$temperature} max_tokens={$max_tokens}");
            aichat_log_debug("[AIChat AJAX][$uid] context raw mode={$context_mode} context_id={$context_id}");

            // 2) Determinar modo de contexto efectivo
            $mode_arg = 'auto';
            if ( $context_mode === 'none' ) { $mode_arg = 'none'; }
            if ( $context_mode === 'page' ) { $mode_arg = 'page'; }

            // page_id llega del frontend para soportar contexto “contenido de la página” en admin-ajax
            $page_id = isset($_POST['page_id']) ? absint($_POST['page_id']) : 0;

            $t_ctx0 = microtime(true);
            $contexts = aichat_get_context_for_question( $message, [
                'context_id' => $context_id,
                'mode'       => $mode_arg, // auto|none|page (auto decide local|pinecone según fila)
                'page_id'    => $page_id,
                'limit'      => 5,
            ] );
            $t_ctx1 = microtime(true);

            $ctx_count = is_array($contexts) ? count($contexts) : 0;
            $ctx_peek = [];
            if ( $ctx_count ) {
                $i = 0;
                foreach ( $contexts as $c ) {
                    $ctx_peek[] = [
                        'title' => isset($c['title']) ? mb_substr( $c['title'], 0, 120 ) : '',
                        'score' => isset($c['score']) ? round( floatval($c['score']), 5 ) : null,
                        'type'  => isset($c['type'])  ? $c['type'] : '',
                        'post'  => isset($c['post_id']) ? (string)$c['post_id'] : '',
                    ];
                    if (++$i >= 3) break;
                }
            }
            aichat_log_debug("[AIChat AJAX][$uid] context mode_eff={$mode_arg} count={$ctx_count} time_ms=" . round( ($t_ctx1 - $t_ctx0)*1000 ) . " top=" . wp_json_encode($ctx_peek) );

            // 3) Construir mensajes (system + historial + user actual)
            // Base (system + user actual con CONTEXTO)
            $base = aichat_build_messages( $message, $contexts, $instructions );
            $system_msg       = isset($base[0]) ? $base[0] : [ 'role'=>'system', 'content'=>'' ];
            $current_user_msg = isset($base[1]) ? $base[1] : [ 'role'=>'user',   'content'=>(string)$message ];

            // Historial previo por sesión+bot, limitado por configuración
            $max_messages_hist  = isset($bot['max_messages']) ? max(1, (int)$bot['max_messages']) : 20;
            $context_max_length = isset($bot['context_max_length']) ? max(128, (int)$bot['context_max_length']) : 4096;
            $history_msgs = $this->build_history_messages( $session, $bot_slug_r, $max_messages_hist, $context_max_length );

            // Mensajes finales: system + historial + user actual
            $messages = array_merge( [ $system_msg ], $history_msgs, [ $current_user_msg ] );

            // Métricas de depuración
            $sys_len = 0; $usr_len = 0;
            foreach ( $messages as $m ) {
                if ( $m['role'] === 'system' ) { $sys_len += mb_strlen( (string)$m['content'] ); }
                if ( $m['role'] === 'user' )   { $usr_len += mb_strlen( (string)$m['content'] ); }
            }
            aichat_log_debug("[AIChat AJAX][$uid] messages built sys_len={$sys_len} user_len={$usr_len}");

            // 4) Claves API
            $openai_key = get_option( 'aichat_openai_api_key', '' );
            $claude_key = get_option( 'aichat_claude_api_key', '' );

            // Normalizar alias de proveedor
            if ($provider === 'anthropic') { $provider = 'claude'; }

            // Normalizar modelo Claude (alias → versión fechada conocida)
            if ($provider === 'claude') {
                $model = $this->normalize_claude_model($model);
            }

            if ( $provider === 'openai' && empty( $openai_key ) ) {
                aichat_log_debug("[AIChat AJAX][$uid] ERROR: OpenAI key missing");
                wp_send_json_error( [ 'message' => __( 'Falta la OpenAI API Key en Ajustes.', 'ai-chat' ) ], 400 );
            }
            if ( $provider === 'claude' && empty( $claude_key ) ) {
                aichat_log_debug("[AIChat AJAX][$uid] ERROR: Claude key missing");
                wp_send_json_error( [ 'message' => __( 'Falta la Claude API Key en Ajustes.', 'ai-chat' ) ], 400 );
            }

            // 5) Llamar al proveedor
            // === USAGE LIMITS (antes de llamar al proveedor) ===
            if ( get_option('aichat_usage_limits_enabled', 1 ) ) {
                global $wpdb; $conv_table = $wpdb->prefix.'aichat_conversations';
                $today_start = gmdate('Y-m-d 00:00:00');
                $today_end   = gmdate('Y-m-d 23:59:59');

                $max_total   = (int) get_option('aichat_usage_max_daily_total', 0); // 0 = sin límite
                $max_per_user= (int) get_option('aichat_usage_max_daily_per_user', 0);
                $msg_user    = trim( (string) get_option('aichat_usage_per_user_message','') );
                $beh_total   = get_option('aichat_usage_daily_total_behavior','disabled'); // disabled|hidden
                $msg_total   = trim( (string) get_option('aichat_usage_daily_total_message','') );

                // Identificador de usuario: preferimos user_id; si 0, usamos hash de IP (no almacenamos hash extra, sólo para comparación en query)
                $user_id_real = get_current_user_id();
                $ip_for_filter = null; $packed_ip = null;
                if ( $user_id_real === 0 ) {
                    // Intentar obtener ip ya calculable igual que en maybe_log_conversation
                    $cands = [];
                    if ( ! empty($_SERVER['HTTP_CF_CONNECTING_IP']) ) $cands[] = $_SERVER['HTTP_CF_CONNECTING_IP'];
                    if ( ! empty($_SERVER['HTTP_X_REAL_IP']) ) $cands[] = $_SERVER['HTTP_X_REAL_IP'];
                    if ( ! empty($_SERVER['HTTP_X_FORWARDED_FOR']) ) $cands[] = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
                    if ( ! empty($_SERVER['REMOTE_ADDR']) ) $cands[] = $_SERVER['REMOTE_ADDR'];
                    foreach ($cands as $c) { $c = trim((string)$c); if ( filter_var($c, FILTER_VALIDATE_IP) ) { $ip_for_filter = $c; break; } }
                    if ( $ip_for_filter ) { $packed_ip = @inet_pton($ip_for_filter); }
                }

                // Comprobar límite por usuario/IP
                if ( $max_per_user > 0 ) {
                    if ( $user_id_real > 0 ) {
                        $count_user = (int)$wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM $conv_table WHERE user_id=%d AND created_at BETWEEN %s AND %s",
                            $user_id_real, $today_start, $today_end
                        ) );
                    } else if ( $packed_ip !== false && $packed_ip !== null ) {
                        // Comparación binaria exacta
                        $count_user = (int)$wpdb->get_var( $wpdb->prepare(
                            "SELECT COUNT(*) FROM $conv_table WHERE ip_address=%s AND created_at BETWEEN %s AND %s",
                            $packed_ip, $today_start, $today_end
                        ) );
                    } else {
                        $count_user = 0; // si no podemos detectar IP, no limitamos anónimo (alternativa: bloquear) 
                    }
                    if ( $count_user >= $max_per_user ) {
                        $limit_msg = $msg_user !== '' ? $msg_user : __( 'Daily message limit reached for this user.', 'ai-chat' );
                        aichat_log_debug("[AIChat AJAX][$uid] per-user/IP limit reached count=$count_user max=$max_per_user");
                        wp_send_json_success( [ 'message' => $limit_msg, 'limited' => true, 'limit_type' => 'per_user' ] );
                    }
                }

                // Comprobar límite global (total)
                if ( $max_total > 0 ) {
                    $count_total = (int)$wpdb->get_var( $wpdb->prepare(
                        "SELECT COUNT(*) FROM $conv_table WHERE created_at BETWEEN %s AND %s",
                        $today_start, $today_end
                    ) );
                    if ( $count_total >= $max_total ) {
                        // Según comportamiento: devolvemos mensaje (disabled) o lo tratamos como oculto
                        aichat_log_debug("[AIChat AJAX][$uid] global limit reached count=$count_total max=$max_total behavior=$beh_total");
                        if ( $beh_total === 'hidden' ) {
                            // Simulamos recurso no disponible
                            wp_send_json_error( [ 'message' => __( 'Chat temporarily unavailable.', 'ai-chat' ), 'limit_type'=>'daily_total_hidden' ], 403 );
                        } else {
                            $limit_msg_total = $msg_total !== '' ? $msg_total : __( 'Daily total message limit reached.', 'ai-chat' );
                            wp_send_json_success( [ 'message' => $limit_msg_total, 'limited' => true, 'limit_type' => 'daily_total' ] );
                        }
                    }
                }
            }
            // === FIN USAGE LIMITS ===

            $t_call0 = microtime(true);
            if ( $provider === 'openai' ) {
                aichat_log_debug("[AIChat AJAX][$uid] calling OpenAI model={$model}");
                $result = $this->call_openai_auto( $openai_key, $model, $messages, $temperature, $max_tokens );
            } elseif ( $provider === 'claude' ) {
                aichat_log_debug("[AIChat AJAX][$uid] calling Claude model={$model}");
                $result = $this->call_claude_messages( $claude_key, $model, $messages, $temperature, $max_tokens );
                if (isset($result['error'])) {
                    aichat_log_debug("[AIChat AJAX][$uid] provider error (Claude): ".$result['error']);
                    wp_send_json_error(['message'=>$result['error']], 500);
                }
                $answer = $result['message'];
            } else {
                wp_send_json_error( [ 'message' => __( 'Provider not supported.', 'ai-chat' ) ], 400 );
            }
            $t_call1 = microtime(true);

            if ( is_wp_error( $result ) ) {
                if ( is_object( $result ) && method_exists( $result, 'get_error_message' ) ) {
                    $error_message = $result->get_error_message();
                } elseif ( is_array( $result ) && isset( $result['error'] ) ) {
                    $error_message = (string) $result['error'];
                } else {
                    $error_message = __( 'Unknown error occurred.', 'ai-chat' );
                }
                aichat_log_debug("[AIChat AJAX][$uid] provider WP_Error: " . $error_message);
                wp_send_json_error( [ 'message' => $error_message ], 500 );
            }
            if ( is_array( $result ) && isset( $result['error'] ) ) {
                aichat_log_debug("[AIChat AJAX][$uid] provider error: " . $result['error']);
                wp_send_json_error( [ 'message' => (string)$result['error'] ], 500 );
            }

            $answer = is_array( $result ) && isset( $result['message'] ) ? (string) $result['message'] : '';
            $ans_preview = mb_substr( $answer, 0, 140 );
            aichat_log_debug("[AIChat AJAX][$uid] raw answer len=" . mb_strlen($answer) . " time_ms=" . round(($t_call1-$t_call0)*1000) . " preview=" . str_replace(array("\n","\r"), ' ', $ans_preview));

            if ( $answer === '' ) {
                aichat_log_debug("[AIChat AJAX][$uid] ERROR: empty answer");
                wp_send_json_error( [ 'message' => __( 'Model returned an empty response.', 'ai-chat' ) ], 500 );
            }

            // 6) Reemplazo [LINK]
            $answer = aichat_replace_link_placeholder( $answer );

            // 6.1) Sanitizar HTML permitido (permitimos <a>, <strong>, <em>, listas, etc.)
            $answer = $this->sanitize_answer_html( $answer );

            // 7) Guardar conversación             
            if ( get_option( 'aichat_logging_enabled', 1 ) ) {
                $this->maybe_log_conversation( get_current_user_id(), $session, $bot_slug, $page_id, $message, $answer );
            }

            // 8) Respuesta (con debug opcional)
            $debug_payload = null;
            if ( $debug ) {
                $debug_payload = [
                    'uid'          => $uid,
                    'bot'          => [ 'id'=>$bot_id, 'slug'=>$bot_slug_r, 'name'=>$bot_name ],
                    'provider'     => $provider,
                    'model'        => $model,
                    'temperature'  => $temperature,
                    'max_tokens'   => $max_tokens,
                    'context_mode' => $context_mode,
                    'context_id'   => $context_id,
                    'mode_effect'  => $mode_arg,
                    'ctx_count'    => $ctx_count,
                    'ctx_top3'     => $ctx_peek,
                    'sys_len'      => $sys_len,
                    'user_len'     => $usr_len,
                    'timings_ms'   => [
                        'context'  => round( ($t_ctx1-$t_ctx0)*1000 ),
                        'provider' => round( ($t_call1-$t_call0)*1000 ),
                        'total'    => round( (microtime(true)-$t0)*1000 ),
                    ],
                ];
                // Cabeceras útiles para inspección en Network → Response Headers
                @header('X-AIChat-Bot: ' . $bot_slug_r);
                @header('X-AIChat-Provider: ' . $provider);
                @header('X-AIChat-Model: ' . $model);
                @header('X-AIChat-Context-Count: ' . $ctx_count);
                @header('X-AIChat-Mode: ' . $mode_arg);
            }

            $resp = [ 'message' => $answer ];
            if ( $debug && $debug_payload ) {
                $resp['debug'] = $debug_payload;
            }

            // Hook after response
            do_action('aichat_after_response', [
            'bot_slug'   => $bot_slug,
            'session_id' => $session,
            'question'   => $message,
            'answer'     => $answer,
            'provider'   => $provider,
            'model'      => $model
            ]);

            wp_send_json_success( $resp );
        }

        /**
         * Resuelve el bot por slug.
         */
        protected function resolve_bot( $bot_slug ) {
            global $wpdb;
            $table = $wpdb->prefix . 'aichat_bots';

            if ( ! empty( $bot_slug ) ) {
                $bot = $wpdb->get_row(
                    $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", $bot_slug ),
                    ARRAY_A
                );
                if ( $bot ) return $bot;
            }

            $global_on   = (bool) get_option( 'aichat_global_bot_enabled', false );
            $global_slug = get_option( 'aichat_global_bot_slug', '' );

            if ( $global_on && ! empty( $global_slug ) ) {
                $bot = $wpdb->get_row(
                    $wpdb->prepare( "SELECT * FROM {$table} WHERE slug = %s LIMIT 1", sanitize_title( $global_slug ) ),
                    ARRAY_A
                );
                if ( $bot ) return $bot;
            }

            // Fallback al primero
            $bot = $wpdb->get_row( "SELECT * FROM {$table} ORDER BY id ASC LIMIT 1", ARRAY_A );
            return $bot ?: null;
        }

        /**
         * OpenAI Chat Completions.
         * $messages en formato [{role, content}, ...]
         */
        protected function call_openai_chat( $api_key, $model, $messages, $temperature, $max_tokens ) {
            $endpoint = 'https://api.openai.com/v1/chat/completions';

            $payload = [
                'model'       => $model,
                'messages'    => array_values( $messages ),
                'temperature' => $temperature,
                'max_tokens'  => $max_tokens,
            ];

            $res = wp_remote_post( $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 45,
                'body'    => wp_json_encode( $payload ),
            ] );

            if ( is_wp_error( $res ) ) {
                return [ 'error' => $res->get_error_message() ];
            }

            $code = wp_remote_retrieve_response_code( $res );
            $body = json_decode( wp_remote_retrieve_body( $res ), true );

            if ( $code >= 400 ) {
                $msg = isset( $body['error']['message'] ) ? $body['error']['message'] : __( 'OpenAI error.', 'ai-chat' );
                return [ 'error' => $msg ];
            }

            $text = $body['choices'][0]['message']['content'] ?? '';
            if ( $text === '' ) {
                return [ 'error' => __( 'Empty response from OpenAI.', 'ai-chat' ) ];
            }

            return [ 'message' => $text ];
        }

        /**
         * Normaliza nombres de modelo Claude a identificadores oficiales versionados.
         * Acepta alias sin fecha y devuelve siempre uno datado.
         */
        protected function normalize_claude_model($model) {
            $m = strtolower(trim((string)$model));
            // Mapa alias → canonical
            $map = [
                'claude-3-5-sonnet-latest' => 'claude-3-5-sonnet-20240620',
                'claude-3-5-sonnet'        => 'claude-3-5-sonnet-20240620',
                'claude-3-5-haiku'         => 'claude-3-5-haiku', // (si existiera se puede actualizar)
                'claude-3-opus'            => 'claude-3-opus-20240229',
                'claude-3-sonnet'          => 'claude-3-sonnet-20240229',
                'claude-3-haiku'           => 'claude-3-haiku-20240307',
            ];
            if (isset($map[$m])) return $map[$m];
            // Si ya es uno oficial con fecha, lo dejamos
            if (preg_match('/^claude-3.*-(20\d{6})$/', $m)) return $m;
            // Fallback seguro
            return 'claude-3-5-sonnet-20240620';
        }

        /**
         * Anthropic Claude (Messages).
         */
        protected function call_claude_messages( $api_key, $model, $messages, $temperature, $max_tokens ) {
            if (empty($api_key)) {
                return ['error' => 'Falta la API Key de Claude'];
            }
            $endpoint = 'https://api.anthropic.com/v1/messages';

            // 1. Separar system y construir bloques Anthropic
            $system_parts = [];
            $claude_msgs  = [];
            foreach ((array)$messages as $m) {
                $role = $m['role'] ?? '';
                $content = $m['content'] ?? '';
                if ($role === 'system') {
                    if (is_array($content)) {
                        $flat = [];
                        foreach ($content as $c) {
                            if (is_string($c)) $flat[] = $c;
                            elseif (is_array($c) && isset($c['text'])) $flat[] = $c['text'];
                        }
                        $system_parts[] = implode("\n\n", $flat);
                    } else {
                        $system_parts[] = (string)$content;
                    }
                    continue;
                }
                if ($role !== 'user' && $role !== 'assistant') continue;

                if (is_array($content)) {
                    $flat = [];
                    foreach ($content as $c) {
                        if (is_string($c)) $flat[] = $c;
                        elseif (is_array($c) && isset($c['text'])) $flat[] = $c['text'];
                    }
                    $content = implode("\n\n", $flat);
                }
                $claude_msgs[] = [
                    'role'    => $role,
                    'content' => [['type'=>'text','text'=>(string)$content]],
                ];
            }
            $system_text = trim(implode("\n\n", array_filter($system_parts)));

            $payload = [
                'model'      => $model,
                'max_tokens' => (int)$max_tokens,
                'messages'   => $claude_msgs,
            ];
            if ($system_text !== '') $payload['system'] = $system_text;
            if ($temperature !== null && $temperature !== '') $payload['temperature'] = (float)$temperature;

            $json_payload = wp_json_encode($payload);

            // Lista de fallback si 404 (model not found)
            $fallback_chain = [];
            $primary = $model;
            // Construir cadena reduciendo “profundidad” (e.g. pasa de sonnet-20240620 → sonnet-20240229 → haiku)
            if ($model !== 'claude-3-5-sonnet-20240620') $fallback_chain[] = 'claude-3-5-sonnet-20240620';
            if ($model !== 'claude-3-sonnet-20240229')   $fallback_chain[] = 'claude-3-sonnet-20240229';
            if ($model !== 'claude-3-haiku-20240307')    $fallback_chain[] = 'claude-3-haiku-20240307';

            $attempts = [$primary, ...$fallback_chain];
            $last_error = null;

            foreach ($attempts as $idx => $mdl_try) {
                if ($mdl_try !== $payload['model']) {
                    $payload['model'] = $mdl_try;
                    $json_payload = wp_json_encode($payload);
                }
                $res = wp_remote_post($endpoint, [
                    'headers' => [
                        'x-api-key'         => $api_key,
                        'anthropic-version' => '2023-06-01',
                        'content-type'      => 'application/json'
                    ],
                    'body'    => $json_payload,
                    'timeout' => 45,
                ]);
                if (is_wp_error($res)) {
                    $last_error = $res->get_error_message();
                    aichat_log_debug('[AIChat Claude][HTTP_ERR] '.$last_error);
                    continue;
                }
                $code   = wp_remote_retrieve_response_code($res);
                $raw    = wp_remote_retrieve_body($res);
                $req_id = wp_remote_retrieve_header($res, 'x-request-id');
                aichat_log_debug('[AIChat Claude][RAW] '.wp_json_encode([
                    'status'=>$code,'model'=>$mdl_try,'req_id'=>$req_id ?: '-',
                    'attempt'=>($idx+1).'/'.count($attempts),
                    'payload_len'=>strlen($json_payload),
                    'resp_len'=>strlen($raw),
                    'resp_preview'=>mb_substr($raw,0,500)
                ]));
                // Si 404 y hay más intentos → probar siguiente
                if ($code === 404 && $idx < count($attempts)-1) {
                    $last_error = '404 model not found: '.$mdl_try;
                    continue;
                }
                // Procesar respuesta normal
                if ($code >= 400) {
                    $data = json_decode($raw, true);
                    $err = '';
                    if (isset($data['error']['message'])) $err = $data['error']['message'];
                    elseif (isset($data['error'])) $err = is_string($data['error']) ? $data['error'] : wp_json_encode($data['error']);
                    else $err = 'HTTP '.$code;
                    $last_error = $err;
                    // No retry salvo 404 (ya tratado)
                    break;
                }
                $data = json_decode($raw, true);
                $text = '';
                if (isset($data['content']) && is_array($data['content'])) {
                    foreach ($data['content'] as $blk) {
                        if (is_array($blk) && ($blk['type'] ?? '') === 'text' && isset($blk['text'])) {
                            $text .= ($text ? "\n\n" : '').trim((string)$blk['text']);
                        }
                    }
                }
                if ($text === '' && isset($data['message']['content']) && is_string($data['message']['content'])) {
                    $text = trim($data['message']['content']);
                }
                if ($text === '') {
                    $last_error = 'Respuesta vacía de Claude (sin bloques).';
                    break;
                }
                // Si hubo fallback exitoso, log y devolver
                if ($mdl_try !== $primary) {
                    aichat_log_debug('[AIChat Claude] Fallback model used: '.$mdl_try.' (original='.$primary.')');
                }
                return ['message'=>$text];
            }
            return ['error'=> $last_error ?: 'Error desconocido Claude'];
        }

        /**
         * Genera una respuesta de un bot de forma programática (sin AJAX/nonce).
         * Uso previsto por integraciones externas (addon WhatsApp, CRON, etc.).
         * NO aplica captcha, honeypot ni rate limit. Añadir fuera si se requiere.
         *
         * @param string $bot_slug   Slug del bot (o vacío para fallback normal).
         * @param string $message    Mensaje del usuario.
         * @param string $session_id ID de sesión (UUID). Si vacío genera uno nuevo.
         * @param array  $args       Opcionales: page_id(int), debug(bool), context_override(array)
         * @return array|WP_Error    ['message','bot_slug','session_id','provider','model', 'debug'?]
         */
        public function process_message_internal( $bot_slug, $message, $session_id, $args = [] ) {
            $message    = (string)$message;
            if ($message === '') return new WP_Error('aichat_empty','Empty message');
            $session_id = preg_replace('/[^a-z0-9\-]/i','', (string)$session_id);
            if ($session_id === '') $session_id = wp_generate_uuid4();
            $bot_slug   = sanitize_title($bot_slug);

            $bot = $this->resolve_bot( $bot_slug );
            if ( ! $bot ) return new WP_Error('aichat_bot_not_found','Bot not found');

            // Normalización básica (mantener sincronizada con process_message)
            $provider     = ! empty( $bot['provider'] ) ? sanitize_key( $bot['provider'] ) : 'openai';
            if ($provider === 'anthropic') $provider = 'claude';
            $model        = ! empty( $bot['model'] ) ? sanitize_text_field( $bot['model'] ) : ( $provider === 'claude' ? 'claude-3-haiku-20240307' : 'gpt-4o-mini' );
            if ($provider === 'claude') { $model = $this->normalize_claude_model($model); }
            $instructions = isset( $bot['instructions'] ) ? wp_kses_post( $bot['instructions'] ) : '';
            $temperature  = isset( $bot['temperature'] ) ? floatval( $bot['temperature'] ) : 0.7;
            if ($temperature < 0) $temperature = 0; if ($temperature > 2) $temperature = 2;
            $max_tokens   = isset( $bot['max_tokens'] ) ? (int)$bot['max_tokens'] : 512; if ($max_tokens <= 0) $max_tokens = 512;
            $context_mode = isset( $bot['context_mode'] ) ? sanitize_key( $bot['context_mode'] ) : 'auto';
            $context_id   = isset( $bot['context_id'] ) ? (int)$bot['context_id'] : 0;
            $page_id      = isset( $args['page_id'] ) ? (int)$args['page_id'] : 0;
            $debug        = ! empty( $args['debug'] );

            $mode_arg = 'auto';
            if ($context_mode === 'none') $mode_arg = 'none';
            if ($context_mode === 'page') $mode_arg = 'page';

            if ( isset($args['context_override']) && is_array($args['context_override']) ) {
                $contexts = $args['context_override'];
            } else {
                $contexts = aichat_get_context_for_question( $message, [
                    'context_id' => $context_id,
                    'mode'       => $mode_arg,
                    'page_id'    => $page_id,
                    'limit'      => 5,
                ] );
            }

            $base = aichat_build_messages( $message, $contexts, $instructions );
            $system_msg       = $base[0] ?? [ 'role'=>'system', 'content'=>'' ];
            $current_user_msg = $base[1] ?? [ 'role'=>'user', 'content'=>$message ];

            $max_messages_hist  = isset($bot['max_messages']) ? max(1,(int)$bot['max_messages']) : 20;
            $context_max_length = isset($bot['context_max_length']) ? max(128,(int)$bot['context_max_length']) : 4096;
            $history_msgs = $this->build_history_messages( $session_id, $bot['slug'], $max_messages_hist, $context_max_length );
            $messages = array_merge( [ $system_msg ], $history_msgs, [ $current_user_msg ] );

            $openai_key = get_option( 'aichat_openai_api_key', '' );
            $claude_key = get_option( 'aichat_claude_api_key', '' );
            if ( $provider === 'openai' && ! $openai_key ) return new WP_Error('aichat_no_key','Missing OpenAI key');
            if ( $provider === 'claude' && ! $claude_key ) return new WP_Error('aichat_no_key','Missing Claude key');

            if ( $provider === 'openai' ) {
                $result = $this->call_openai_auto( $openai_key, $model, $messages, $temperature, $max_tokens );
            } elseif ( $provider === 'claude' ) {
                $result = $this->call_claude_messages( $claude_key, $model, $messages, $temperature, $max_tokens );
            } else {
                return new WP_Error('aichat_provider','Provider not supported');
            }
            if ( is_wp_error($result) ) return $result;
            if ( isset($result['error']) ) return new WP_Error('aichat_provider_error', (string)$result['error']);

            $answer = (string)($result['message'] ?? '');
            if ($answer === '') return new WP_Error('aichat_empty_answer','Empty answer');
            $answer = aichat_replace_link_placeholder( $answer );
            $answer = $this->sanitize_answer_html( $answer );

            if ( get_option( 'aichat_logging_enabled', 1 ) ) {
                $this->maybe_log_conversation( get_current_user_id(), $session_id, $bot['slug'], $page_id, $message, $answer );
            }

            do_action( 'aichat_after_response', [
                'bot_slug'   => $bot['slug'],
                'session_id' => $session_id,
                'question'   => $message,
                'answer'     => $answer,
                'provider'   => $provider,
                'model'      => $model,
                'internal'   => true,
            ] );

            $out = [
                'message'    => $answer,
                'bot_slug'   => $bot['slug'],
                'session_id' => $session_id,
                'provider'   => $provider,
                'model'      => $model,
            ];
            if ($debug) $out['debug'] = [ 'context_count'=> is_array($contexts)?count($contexts):0 ];
            return $out;
        }

        /**
         * Guarda conversación si la tabla existe.
         */
        protected function maybe_log_conversation( $user_id, $session_id, $bot_slug, $page_id, $q, $a ) {
            if ( ! get_option( 'aichat_logging_enabled', 1 ) ) {
                return;
            }
            global $wpdb;
            $table = $wpdb->prefix . 'aichat_conversations';

            $exists = $wpdb->get_var( $wpdb->prepare(
                "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = %s",
                $table
            ) );
            if ( intval( $exists ) !== 1 ) { return; }

            // Obtener IP del usuario (preferimos REMOTE_ADDR; opcionalmente cabeceras proxy confiables)
            $ip_raw = '';
            $candidates = [];
            if ( ! empty( $_SERVER['HTTP_CF_CONNECTING_IP'] ) ) { $candidates[] = $_SERVER['HTTP_CF_CONNECTING_IP']; }
            if ( ! empty( $_SERVER['HTTP_X_REAL_IP'] ) ) { $candidates[] = $_SERVER['HTTP_X_REAL_IP']; }
            if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) { $candidates[] = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0]; }
            if ( ! empty( $_SERVER['REMOTE_ADDR'] ) ) { $candidates[] = $_SERVER['REMOTE_ADDR']; }
            foreach ( $candidates as $cand ) {
                $cand = trim( (string)$cand );
                if ( filter_var( $cand, FILTER_VALIDATE_IP ) ) { $ip_raw = $cand; break; }
            }
            $ip_binary = null;
            if ( $ip_raw ) {
                // inet_pton soporta IPv4 e IPv6; devuelve binario o false
                $packed = @inet_pton( $ip_raw );
                if ( $packed !== false ) { $ip_binary = $packed; }
            }

            $data = [
                'user_id'    => intval( $user_id ),
                'session_id' => $session_id,
                'bot_slug'   => sanitize_title($bot_slug),
                'page_id'    => absint($page_id),
                'message'    => wp_kses_post( $q ),
                'response'   => wp_kses_post( $a ),
                'created_at' => current_time( 'mysql' ),
            ];
            $formats = [ '%d','%s','%s','%d','%s','%s','%s' ];
            // Sólo añadimos ip_address si la columna existe
            $has_ip = $wpdb->get_var( $wpdb->prepare("SHOW COLUMNS FROM `$table` LIKE %s", 'ip_address') );
            if ( $has_ip ) {
                $data['ip_address'] = $ip_binary; // puede ser null
                $formats[] = '%s'; // WordPress no tiene formato binario específico; %s funciona para VARBINARY
            }
            $wpdb->insert( $table, $data, $formats );

            // Hook after insert
            if ( ! empty( $wpdb->insert_id ) ) {
            do_action('aichat_conversation_saved', [
                'id'        => $wpdb->insert_id,
                'bot_slug'  => $bot_slug,
                'session_id'=> $session_id,
                'user_id'   => $user_id,
                'page_id'   => $page_id
            ]);
            }

        }

        /**
         * Devuelve historial de conversación por session_id + bot_slug.
         */
        public function get_history() {
            $nonce = isset($_POST['nonce']) ? sanitize_text_field( wp_unslash($_POST['nonce']) ) : '';
            if ( empty($nonce) || ! wp_verify_nonce( $nonce, 'aichat_ajax' ) ) {
                wp_send_json_error( [ 'message' => __( 'Nonce inválido.', 'ai-chat' ) ], 403 );
            }
            $session  = isset( $_POST['session_id'] ) ? preg_replace('/[^a-z0-9\-]/i','', (string)wp_unslash($_POST['session_id'])) : '';
            $bot_slug = isset( $_POST['bot_slug'] ) ? sanitize_title( wp_unslash( $_POST['bot_slug'] ) ) : '';
            $limit    = isset( $_POST['limit'] ) ? max(1, min(200, intval($_POST['limit']) )) : 50;
            if ($session==='' || $bot_slug==='') wp_send_json_success( [ 'items' => [] ] );

            global $wpdb; $t = $wpdb->prefix.'aichat_conversations';
            $rows = $wpdb->get_results(
                $wpdb->prepare("SELECT message, response, created_at FROM $t WHERE session_id=%s AND bot_slug=%s ORDER BY id ASC LIMIT %d", $session, $bot_slug, $limit),
                ARRAY_A
            );
            // Ya guardamos sanitizado; evitamos doble-escape en cliente.
            wp_send_json_success( [ 'items' => array_map(function($r){
                return [
                    'q' => (string)$r['message'],
                    'a' => (string)$r['response'],
                    't' => (string)$r['created_at'],
                ];
            }, $rows ?: []) ] );
        }

        /**
         * Permite un HTML básico en la respuesta del bot (enlaces seguros, formateo simple).
         */
        protected function sanitize_answer_html( $html ) {
            $allowed = [
                'a'      => [ 'href' => true, 'target' => true, 'rel' => true, 'title' => true ],
                'strong' => [], 'em' => [], 'b' => [], 'i' => [],
                'br'     => [], 'p' => [], 'ul' => [], 'ol' => [], 'li' => [],
                'code'   => [], 'pre' => [],
                'span'   => [ 'class' => true ],
            ];
            // Forzar rel noopener en target=_blank
            $html = preg_replace('/<a([^>]+)target="_blank"/i', '<a$1target="_blank" rel="noopener"', $html);
            return wp_kses( $html, $allowed );
        }

        /**
         * Construye el historial en formato [{role:'user'},{role:'assistant'},...]
         * Limita nº de pares por max_pairs y recorta a un presupuesto de caracteres ($char_limit).
         */
        protected function build_history_messages( $session_id, $bot_slug, $max_pairs, $char_limit ) {
            global $wpdb;
            $t = $wpdb->prefix.'aichat_conversations';
            $rows = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT message, response FROM $t WHERE session_id=%s AND bot_slug=%s ORDER BY id DESC LIMIT %d",
                    $session_id, $bot_slug, max(1,(int)$max_pairs)
                ),
                ARRAY_A
            );
            if ( ! $rows ) return [];

            $rows = array_reverse($rows); // cronológico
            $out = []; $acc = 0;
            foreach ($rows as $r) {
                $q = trim( wp_strip_all_tags( (string)$r['message'] ) );
                $a = trim( wp_strip_all_tags( (string)$r['response'] ) );
                if ($q !== '') {
                    $q = $this->truncate_to_budget($q, $char_limit, $acc);
                    if ($q === '') break;
                    $out[] = [ 'role'=>'user', 'content'=>$q ];
                    $acc += strlen($q);
                }
                if ($a !== '') {
                    $a = $this->truncate_to_budget($a, $char_limit, $acc);
                    if ($a === '') break;
                    $out[] = [ 'role'=>'assistant', 'content'=>$a ];
                    $acc += strlen($a);
                }
                if ($acc >= $char_limit) break;
            }
            return $out;
        }

        protected function truncate_to_budget( $text, $limit, $used ) {
            if ($limit <= 0) return (string)$text;
            $remain = $limit - $used;
            if ($remain <= 0) return '';
            $s = (string)$text;
            return (strlen($s) > $remain) ? substr($s, 0, $remain) : $s;
        }

        /**
         * Nuevo: Router OpenAI. Modelos gpt-5* → Responses API; resto → Chat Completions.
         */
        protected function call_openai_auto( $api_key, $model, $messages, $temperature, $max_tokens, $extra = [] ) {
            if ( $this->is_openai_responses_model( $model ) ) {
                return $this->call_openai_responses( $api_key, $model, $messages, $max_tokens, $extra, $temperature );
            }
            return $this->call_openai_chat_cc( $api_key, $model, $messages, $temperature, $max_tokens );
        }

        protected function is_openai_responses_model( $model ) {
            $m = strtolower((string)$model);
            return strpos($m, 'gpt-5') === 0; // gpt‑5* → Responses API
        }

        protected function is_gpt5_model( $model ) {
            return (bool)preg_match('/^gpt-5(\b|[-_])/i', (string)$model);
        }

        protected function map_reasoning_effort($val) {
            $v = strtolower((string)$val);
            if ($v === 'fast') return 'low';
            if ($v === 'accurate') return 'high';
            return 'medium'; // por si se amplía en el futuro
        }

        /**
         * OpenAI Responses API con auto-fallback de parámetros.
         * Nota: el orden de los args mantiene compatibilidad con call_openai_auto().
         */
        protected function call_openai_responses( $api_key, $model, $messages, $max_tokens, $extra = [], $temperature = null ) {
            $endpoint = 'https://api.openai.com/v1/responses';
            $input    = $this->messages_to_prompt( (array)$messages );
            $is_gpt5  = $this->is_gpt5_model($model);

            // Construcción inicial del payload (sin temperature para gpt‑5*)
            $payload = [
                'model' => $model,
                'input' => $input,
            ];

            // Reasoning (solo si UI ≠ off); lo quitamos si el modelo lo rechaza
            if (!empty($extra['reasoning']) && strtolower($extra['reasoning']) !== 'off') {
                $payload['reasoning'] = [ 'effort' => $this->map_reasoning_effort($extra['reasoning']) ];
            }

            // Temperature: jamás enviar para gpt‑5*
            if (!$is_gpt5 && $temperature !== null && $temperature !== '') {
                $payload['temperature'] = (float)$temperature;
            }

            // Preferir max_output_tokens; si el backend lo rechaza, caemos a max_tokens
            if (!empty($max_tokens)) {
                $payload['max_output_tokens'] = (int)$max_tokens;
            }

            $post = function(array $pl) use ($endpoint, $api_key) {
                return wp_remote_post( $endpoint, [
                    'headers' => [
                        'Authorization' => 'Bearer ' . $api_key,
                        'Content-Type'  => 'application/json',
                    ],
                    'timeout' => 45,
                    'body'    => wp_json_encode( $pl ),
                ]);
            };

            $res = $post($payload);
            if ( is_wp_error($res) ) return [ 'error' => $res->get_error_message() ];
            $code = wp_remote_retrieve_response_code($res);
            $body_raw = wp_remote_retrieve_body($res);
            $body = json_decode($body_raw, true);

            // Fallbacks por parámetros no soportados
            if ($code >= 400) {
                $msg = isset($body['error']['message']) ? (string)$body['error']['message'] : '';

                // Quitar temperature si lo rechaza
                if (stripos($msg, 'temperature') !== false && isset($payload['temperature'])) {
                    unset($payload['temperature']);
                    $res = $post($payload);
                    if ( is_wp_error($res) ) return [ 'error' => $res->get_error_message() ];
                    $code = wp_remote_retrieve_response_code($res);
                    $body_raw = wp_remote_retrieve_body($res);
                    $body = json_decode($body_raw, true);
                }

                // Cambiar max_output_tokens → max_tokens si lo rechaza
                if ($code >= 400) {
                    $msg = isset($body['error']['message']) ? (string)$body['error']['message'] : $msg;
                    if (stripos($msg, 'max_output_tokens') !== false && isset($payload['max_output_tokens'])) {
                        $payload['max_tokens'] = (int)$payload['max_output_tokens'];
                        unset($payload['max_output_tokens']);
                        $res = $post($payload);
                        if ( is_wp_error($res) ) return [ 'error' => $res->get_error_message() ];
                        $code = wp_remote_retrieve_response_code($res);
                        $body_raw = wp_remote_retrieve_body($res);
                        $body = json_decode($body_raw, true);
                    }
                }

                // Quitar reasoning si lo rechaza
                if ($code >= 400) {
                    $msg = isset($body['error']['message']) ? (string)$body['error']['message'] : $msg;
                    if (stripos($msg, 'reasoning') !== false && isset($payload['reasoning'])) {
                        unset($payload['reasoning']);
                        $res = $post($payload);
                        if ( is_wp_error($res) ) return [ 'error' => $res->get_error_message() ];
                        $code = wp_remote_retrieve_response_code($res);
                        $body_raw = wp_remote_retrieve_body($res);
                        $body = json_decode($body_raw, true);
                    }
                }
            }

            // Log (ya lo tienes justo antes)
            // Extracción robusta del texto
            $text = $this->extract_openai_responses_text($body);

            // Fallback por si alguna cuenta devuelve formato tipo chat
            if ($text === '' && isset($body['choices'][0]['message']['content'])) {
                $text = (string)$body['choices'][0]['message']['content'];
            }

            if ($text === '') {
                return [ 'error' => __( 'Empty response from OpenAI (Responses).', 'ai-chat' ) ];
            }
            return [ 'message' => (string)$text ];
        }

        /** Chat Completions clásico para GPT‑4* (versión router) */
        protected function call_openai_chat_cc( $api_key, $model, $messages, $temperature, $max_tokens ) {
            $endpoint = 'https://api.openai.com/v1/chat/completions';
            $payload = [
                'model'       => $model,
                'messages'    => array_values($messages),
                'temperature' => (float)$temperature,
                'max_tokens'  => (int)$max_tokens,
            ];
            $res = wp_remote_post( $endpoint, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $api_key,
                    'Content-Type'  => 'application/json',
                ],
                'timeout' => 45,
                'body'    => wp_json_encode($payload),
            ]);
            if ( is_wp_error($res) ) return [ 'error' => $res->get_error_message() ];
            $code = wp_remote_retrieve_response_code($res);
            $body = json_decode( wp_remote_retrieve_body($res), true );
            if ($code >= 400) {
                $msg = isset($body['error']['message']) ? $body['error']['message'] : __('Error en OpenAI.','ai-chat');
                return [ 'error' => $msg ];
            }
            $text = $body['choices'][0]['message']['content'] ?? '';
            if ($text === '') return [ 'error' => __('Respuesta vacía de OpenAI.','ai-chat') ];
            return [ 'message' => (string)$text ];
        }

        /**
         * Convierte [{role,content}] a prompt plano para Responses.
         */
        protected function messages_to_prompt( $messages ) {
            $sys = []; $lines = [];
            foreach ($messages as $m) {
                $role = $m['role'] ?? 'user';
                $txt  = trim((string)($m['content'] ?? ''));
                if ($txt==='') continue;
                if ($role === 'system') { $sys[] = $txt; continue; }
                $prefix = ($role === 'assistant') ? 'Assistant' : 'User';
                $lines[] = $prefix . ': ' . $txt;
            }
            $sysBlock = !empty($sys) ? ("System: " . implode("\n\n", $sys) . "\n\n") : '';
            return $sysBlock . implode("\n\n", $lines);
        }

        /**
         * Registra respuesta de OpenAI (intentos, errores, etc.).
         */
        protected function aichat_log_openai_response($kind, $endpoint, $model, $status, $res, $body_raw, $payload_meta = [], $input_len = null) {
            try {
                $req_id = wp_remote_retrieve_header($res, 'x-request-id');
                if (!$req_id) { $req_id = wp_remote_retrieve_header($res, 'x-openai-request-id'); }
                $body_trim = is_string($body_raw) ? mb_substr($body_raw, 0, 4000) : '';
                $usage = '';
                $decoded = json_decode($body_raw, true);
                if (is_array($decoded)) {
                    if (isset($decoded['usage'])) {
                        $usage = ' usage=' . wp_json_encode($decoded['usage']);
                    } elseif (isset($decoded['output_tokens'])) {
                        $usage = ' output_tokens=' . (int)$decoded['output_tokens'];
                    }
                }
                $meta = $payload_meta;
                if ($input_len !== null) { $meta['input_len'] = (int)$input_len; }
                aichat_log_debug(
                    '[AIChat AJAX][OpenAI '.$kind.']'.
                    ' model='.$model.
                    ' status='.$status.
                    ' req_id='.($req_id ?: '-').
                    $usage.
                    ' endpoint='.$endpoint.
                    ' meta='.wp_json_encode($meta).
                    ' body_raw='.$body_trim
                );
            } catch (\Throwable $e) {
                aichat_log_debug('[AIChat AJAX][OpenAI '.$kind.'] log error: '.$e->getMessage());
            }
        }

        protected function extract_openai_responses_text(array $body) {
            // 1) Campo directo si existe
            if (!empty($body['output_text']) && is_string($body['output_text'])) {
                return trim((string)$body['output_text']);
            }

            // 2) Recorrer output[] buscando type=message y sus content[]
            if (!empty($body['output']) && is_array($body['output'])) {
                $buf = '';
                foreach ($body['output'] as $item) {
                    if (!is_array($item)) continue;
                    // Algunos items pueden ser de tipo "reasoning" sin texto; saltar
                    if (($item['type'] ?? '') !== 'message') continue;

                    $content = $item['content'] ?? null;
                    if (is_string($content) && $content !== '') {
                        $buf .= ($buf ? "\n\n" : '') . trim($content);
                        continue;
                    }
                    if (is_array($content)) {
                        foreach ($content as $c) {
                            if (!is_array($c)) continue;
                            $ctype = strtolower((string)($c['type'] ?? ''));
                            // output_text y text son los más comunes
                            if (($ctype === 'output_text' || $ctype === 'text') && !empty($c['text'])) {
                                $buf .= ($buf ? "\n\n" : '') . trim((string)$c['text']);
                            }
                        }
                    }
                }
                if ($buf !== '') return $buf;
            }

            // 3) Algunos backends devuelven message.content plano
            if (!empty($body['message']['content']) && is_string($body['message']['content'])) {
                return trim((string)$body['message']['content']);
            }

            // 4) Sin texto localizado
            return '';
        }
    }

    if ( ! class_exists( 'AICHAT_AJAX', false ) ) {
        class_alias( 'AIChat_Ajax', 'AICHAT_AJAX' );
    }
}


// Función externa para generar respuesta del bot (para uso en themes, plugins, etc.)
function aichat_generate_bot_response( $bot_slug, $message, $args = [] ) {
  // $args: session_id (opcional), page_id (opcional), debug (opcional)
  if ( ! class_exists('AIChat_Ajax') ) return new WP_Error('aichat_missing','AI Chat not loaded');

  $session_id = isset($args['session_id']) ? preg_replace('/[^a-z0-9\\-]/i','',$args['session_id']) : '';
  if ( $session_id === '' ) { $session_id = wp_generate_uuid4(); }

  // Reutiliza internamente la lógica: sugerido refactor (extract a private core method)
  // Versión rápida: llama a un nuevo método público reducido
  return AIChat_Ajax::instance()->process_message_internal($bot_slug, $message, $session_id, $args);
}

/**
 * Genera una respuesta usando un número de teléfono externo (WhatsApp u otro canal) como base de sesión.
 * No crea tablas nuevas: simplemente usa session_id determinista "wha<hash|digits>" para agrupar historial.
 * @param string $bot_slug
 * @param string $phone  Número en formato internacional sugerido (solo dígitos y '+').
 * @param string $message Texto del usuario.
 * @param array  $args    Acepta page_id, debug. session_id se ignora (se fuerza).
 * @return array|WP_Error
 */
function aichat_generate_bot_response_for_phone( $bot_slug, $phone, $message, $args = [] ) {
        if ( ! class_exists('AIChat_Ajax') ) return new WP_Error('aichat_missing','AI Chat not loaded');
        // Normalizar teléfono: dejamos dígitos y '+' inicial si existe
        $raw = trim((string)$phone);
        $normalized = preg_replace('/[^0-9+]/','', $raw);
        if ($normalized === '') return new WP_Error('aichat_phone_empty','Empty phone');
        // Acotar longitud para prevenir abusos (guardamos máximo 24 chars visibles)
        if (strlen($normalized) > 24) { $normalized = substr($normalized,0,24); }
        // session determinista: wha<md5> o wha<digits>. (Antes se usaba 'wha_' con guion bajo; soportado en vistas).
        $digits_only = preg_replace('/[^0-9]/','', $normalized);
        if ($digits_only === '') { $digits_only = substr(md5($normalized),0,10); }
        $session_id = 'wha'.$digits_only;
        // Forzar session_id
        $args['session_id'] = $session_id;
        return aichat_generate_bot_response( $bot_slug, $message, $args );
}
