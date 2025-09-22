<?php
if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Enqueue Bootstrap y custom scripts
add_action( 'admin_enqueue_scripts', 'aichat_admin_enqueue_scripts' );
function aichat_admin_enqueue_scripts($hook) {

    // Registrar solo una vez
    wp_register_style(
        'aichat-bootstrap',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/css/bootstrap.min.css',
        [],
        '5.3.0'
    );
    wp_register_script(
        'aichat-bootstrap',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap/js/bootstrap.bundle.min.js',
        [],
        '5.3.0',
        true
    );
    wp_register_style(
        'aichat-bootstrap-icons',
        AICHAT_PLUGIN_URL . 'assets/vendor/bootstrap-icons/font/bootstrap-icons.css',
        [],
        '1.11.3'
    );

    // Encolar en tus páginas de contexto
    if (strpos((string)$hook, 'aichat-contexto') !== false) {
        wp_enqueue_style('aichat-bootstrap');
        wp_enqueue_style('aichat-bootstrap-icons');
        wp_enqueue_script('aichat-bootstrap');
    }

    // Encolar para la página de creación (pestaña 1)
    if ($hook === 'admin_page_aichat-contexto-create') {
        wp_enqueue_script(
            'aichat-contexto-create',
            plugin_dir_url(__FILE__) . '../assets/js/contexto-create.js',
            array('jquery'),
            null,
            true
        );
        wp_localize_script(
            'aichat-contexto-create',
            'aichat_create_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aichat_nonce'),
                'has_woocommerce' => class_exists('WooCommerce') ? 1 : 0,
            )
        );
    }

    // Encolar para la página de settings (pestaña 2)
    if ($hook === 'ai-chat_page_aichat-contexto-settings') {        
        wp_enqueue_script(
            'aichat-contexto-settings',
            plugin_dir_url(__FILE__) . '../assets/js/contexto-settings.js',
            array('jquery'),
            null,
            true
        );
        wp_localize_script(
            'aichat-contexto-settings',
            'aichat_settings_ajax',
            array(
                'ajax_url' => admin_url('admin-ajax.php'),
                'nonce' => wp_create_nonce('aichat_nonce'),
                'edit_text' => __('Edit', 'aichat'),
                'delete_text' => __('Delete', 'aichat'),
                'delete_confirm' => __('Are you sure you want to delete this context?', 'aichat'),
                'updated_text' => __('Context name updated.', 'aichat'),
                'deleted_text' => __('Context deleted.', 'aichat')
            )
        );
    }
}

// Añadir opción para RAG
add_action( 'admin_init', 'aichat_register_contexto_settings' );
function aichat_register_contexto_settings() {
    register_setting( 'aichat_contexto_group', 'aichat_rag_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'rest_sanitize_boolean',
        'default' => false
    ] );
    register_setting( 'aichat_contexto_group', 'aichat_selected_items', [
        'type' => 'array',
        'sanitize_callback' => 'aichat_sanitize_selected_items',
        'default' => []
    ] );
    register_setting( 'aichat_contexto_group', 'aichat_select_posts_mode', [
        'sanitize_callback' => 'aichat_sanitize_select_mode',
        'default' => ''
    ]);
    register_setting( 'aichat_contexto_group', 'aichat_select_pages_mode', [
        'sanitize_callback' => 'aichat_sanitize_select_mode',
        'default' => ''
    ]);
    register_setting( 'aichat_contexto_group', 'aichat_select_products_mode', [
        'sanitize_callback' => 'aichat_sanitize_select_mode',
        'default' => ''
    ]);
    register_setting( 'aichat_contexto_group', 'aichat_active_context', [
        'type' => 'integer',
        'sanitize_callback' => 'absint',
        'default' => 0
    ]);
}

/**
 * Sanitize selected items (IDs).
 */
function aichat_sanitize_selected_items( $input ) {
    $sanitized = [];
    foreach ( (array) $input as $id ) {
        $sanitized[] = absint( $id );
    }
    return array_unique( $sanitized );
}

/**
 * Sanitize select mode (all, custom, or empty).
 */
function aichat_sanitize_select_mode( $input ) {
    $valid_modes = ['', 'all', 'custom'];
    return in_array( $input, $valid_modes, true ) ? $input : '';
}

// Función para indexar post
function aichat_index_post( $post_id, $context_id = 0 ) {
    $post_id    = (int) $post_id;
    $context_id = (int) $context_id;

    $post = get_post( $post_id );
    if ( ! $post || $post->post_status !== 'publish' ) {
        return false;
    }

    $text       = wp_strip_all_tags( $post->post_title . "\n" . $post->post_content );
    if ( $text === '' ) {
        return false;
    }

    // Genera embedding (pon tu propia función)
    $embedding = aichat_generate_embedding( $text );
    if ( ! is_array( $embedding ) || empty( $embedding ) ) {
        return false;
    }

    $table   = $GLOBALS['wpdb']->prefix . 'aichat_chunks';
    $type    = $post->post_type;
    $title   = $post->post_title;
    $content = $text;
    $embed   = wp_json_encode( array_values( $embedding ) ); // JSON limpio
    $tokens  = str_word_count( $text ); // métrica aproximada (no “tokens” de LLM)

    // Requiere UNIQUE (post_id, id_context)
    $sql = $GLOBALS['wpdb']->prepare(
        "INSERT INTO `$table`
            (`post_id`, `id_context`, `type`, `title`, `content`, `embedding`, `tokens`, `updated_at`)
         VALUES
            (%d, %d, %s, %s, %s, %s, %d, NOW())
         ON DUPLICATE KEY UPDATE
            `type`      = VALUES(`type`),
            `title`     = VALUES(`title`),
            `content`   = VALUES(`content`),
            `embedding` = VALUES(`embedding`),
            `tokens`    = VALUES(`tokens`),
            `updated_at`= VALUES(`updated_at`)",
        $post_id, $context_id, $type, $title, $content, $embed, $tokens
    );

    $r = $GLOBALS['wpdb']->query( $sql );
    return $r !== false;
}


// ==============================
// === Embeddings & Context  ====
// ==============================

/**
 * Genera embedding con OpenAI.
 */
function aichat_generate_embedding( $text ) {
    $api_key = get_option( 'aichat_openai_api_key' );
    if ( empty( $api_key ) ) {
        return null;
    }

    $body = wp_json_encode( [ 'input' => $text, 'model' => 'text-embedding-3-small' ] );
    $response = wp_remote_post( 'https://api.openai.com/v1/embeddings', [
        'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
        'body'    => $body,
        'timeout' => 25,
    ] );

    if ( is_wp_error( $response ) ) {
        error_log( '[AIChat] Embedding error: ' . $response->get_error_message() );
        return null;
    }

    $json = json_decode( wp_remote_retrieve_body( $response ), true );
    return $json['data'][0]['embedding'] ?? null;
}

/**
 * Similaridad coseno entre dos vectores.
 */
function aichat_cosine_similarity( $vec1, $vec2 ) {
    $dot = $norm1 = $norm2 = 0.0;
    $n = min( count( $vec1 ), count( $vec2 ) );
    for ( $i = 0; $i < $n; $i++ ) {
        $a = (float) $vec1[ $i ];
        $b = (float) $vec2[ $i ];
        $dot   += $a * $b;
        $norm1 += $a * $a;
        $norm2 += $b * $b;
    }
    if ( $norm1 == 0.0 || $norm2 == 0.0 ) { return 0.0; }
    return $dot / ( sqrt( $norm1 ) * sqrt( $norm2 ) );
}

/**
 * Obtiene contexto para una pregunta.
 *
 * @param string $question
 * @param array  $args {
 *   @type int    $context_id  ID concreto del contexto. Si no viene, usa aichat_active_context (auto).
 *   @type string $mode        'auto' | 'local' | 'pinecone' | 'none' | 'page'
 *   @type int    $limit       nº de chunks a devolver (def 5)
 *   @type int    $page_id     ID de la página/post actual (cuando mode=page)
 * }
 * @return array lista de filas con claves: post_id, title, content, score, (type si local), ...
 */
function aichat_get_context_for_question( $question, $args = [] ) {
    $defaults = [
        'context_id' => 0,
        'mode'       => 'auto',
        'limit'      => 5,
        'page_id'    => 0,
    ];
    $args = wp_parse_args( $args, $defaults );
    $limit = max( 1, intval( $args['limit'] ) );

    // Modo none: sin contexto
    if ( $args['mode'] === 'none' ) {
        $GLOBALS['contexts'] = [];
        return [];
    }

    $q_embed = aichat_generate_embedding( $question );
    if ( ! $q_embed ) {
        $GLOBALS['contexts'] = [];
        return [];
    }

    global $wpdb;

    // Resolver context_id si no viene
    $context_id = intval( $args['context_id'] );
    if ( $context_id <= 0 ) {
        $context_id = intval( get_option( 'aichat_active_context', 0 ) );
    }

    // Si no hay contexto definido y mode es auto/local → no hay contexto
    if ( $context_id <= 0 && $args['mode'] !== 'pinecone' ) {
        $GLOBALS['contexts'] = [];
        return [];
    }

    // Si hay que decidir automáticamente si es remoto/local, consultamos la tabla de contextos
    $context_row = null;
    if ( $context_id > 0 ) {
        $context_row = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}aichat_contexts WHERE id = %d", $context_id ),
            ARRAY_A
        );
    }

    $mode = $args['mode'];
    if ( $mode === 'auto' && $context_row ) {
        if ( $context_row['context_type'] === 'remoto' && $context_row['remote_type'] === 'pinecone'
             && ! empty( $context_row['remote_api_key'] ) && ! empty( $context_row['remote_endpoint'] ) ) {
            $mode = 'pinecone';
        } else {
            $mode = 'local';
        }
    } elseif ( $mode === 'auto' && ! $context_row ) {
        // Sin fila de contexto: no hay contexto
        $GLOBALS['contexts'] = [];
        return [];
    }

    // --------- Page content (contenido de la página/post actual) ----------
    if ( $mode === 'page' ) {
        $pid = intval($args['page_id']);
        if ($pid <= 0) {
            // fallback débil: intentar el objeto consultado si no es admin-ajax (normalmente no habrá)
            $pid = function_exists('get_queried_object_id') ? intval(get_queried_object_id()) : 0;
        }
        if ($pid > 0) {
            $post = get_post($pid);
            if ($post && $post->post_status === 'publish') {
                $text = wp_strip_all_tags( $post->post_title . "\n" . $post->post_content );
                $row = [
                    'post_id' => (int)$post->ID,
                    'title'   => (string)$post->post_title,
                    'content' => (string)$text,
                    'score'   => 1.0,
                    'type'    => (string)$post->post_type,
                ];
                $GLOBALS['contexts'] = [ $row ];
                return [ $row ];
            }
        }
        $GLOBALS['contexts'] = [];
        return [];
    }

    // --------- Pinecone ----------
    if ( $mode === 'pinecone' ) {
        if ( ! $context_row || empty( $context_row['remote_api_key'] ) || empty( $context_row['remote_endpoint'] ) ) {
            $GLOBALS['contexts'] = [];
            return [];
        }

        $api_key  = $context_row['remote_api_key'];
        $raw_ep   = trim( (string)$context_row['remote_endpoint'] );

        // Sanitizar remote_endpoint
        $remote_endpoint = aichat_sanitize_remote_endpoint( $raw_ep );
        if ( $remote_endpoint === '' ) {
            error_log('[AIChat] Invalid remote_endpoint discarded: '. $raw_ep);
            $GLOBALS['contexts'] = [];
            return [];
        }

        $endpoint = rtrim( $remote_endpoint, '/' ) . '/query';

        $response = wp_remote_post( $endpoint, [
            'headers' => [ 'Api-Key' => $api_key, 'Content-Type' => 'application/json' ],
            'body'    => wp_json_encode( [
                'vector'           => array_values( $q_embed ),
                'top_k'            => $limit,
                'include_values'   => false,
                'include_metadata' => true,
                'namespace'        => 'aichat_context_' . $context_id,
            ] ),
            'timeout' => 25,
        ] );

        if ( is_wp_error( $response ) ) {
            error_log( '[AIChat] Pinecone query error: ' . $response->get_error_message() );
            $GLOBALS['contexts'] = [];
            return [];
        }

        $code = wp_remote_retrieve_response_code( $response );
        if ( $code !== 200 ) {
            error_log( '[AIChat] Pinecone HTTP ' . $code . ' → ' . wp_remote_retrieve_body( $response ) );
            $GLOBALS['contexts'] = [];
            return [];
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );
        $rows = array_map( function( $m ) {
            return [
                'post_id' => isset( $m['id'] ) ? $m['id'] : 0,
                'title'   => $m['metadata']['title']  ?? '',
                'content' => $m['metadata']['content']?? '',
                'score'   => $m['score']              ?? 0,
                'type'    => $m['metadata']['type']   ?? '',
            ];
        }, $data['matches'] ?? [] );

        usort( $rows, fn($a,$b) => $b['score'] <=> $a['score'] );
        $GLOBALS['contexts'] = $rows;
        return array_slice( $rows, 0, $limit );
    }

    // --------- Local DB ----------
    if ( $mode === 'local' ) {
        $table = $wpdb->prefix . 'aichat_chunks';
        $rows  = $wpdb->get_results(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id_context = %d", $context_id ),
            ARRAY_A
        );

        foreach ( $rows as &$row ) {
            $emb = json_decode( $row['embedding'], true );
            $row['score'] = is_array( $emb ) ? aichat_cosine_similarity( $q_embed, $emb ) : 0.0;
        }
        unset( $row );

        usort( $rows, fn($a,$b) => $b['score'] <=> $a['score'] );
        $rows = array_slice( $rows, 0, $limit );

        // Normaliza claves como en pinecone
        $norm = array_map( function($r){
            return [
                'post_id' => $r['post_id'] ?? 0,
                'title'   => $r['title']    ?? '',
                'content' => $r['content']  ?? '',
                'score'   => $r['score']    ?? 0,
                'type'    => $r['type']     ?? '',
            ];
        }, $rows );

        $GLOBALS['contexts'] = $norm;
        return $norm;
    }

    // Cualquier otro caso: sin contexto
    $GLOBALS['contexts'] = [];
    return [];
}

/**
 * Construye el array de mensajes (system + user) con instrucciones y contexto.
 *
 * @param string $question
 * @param array  $contexts lista devuelta por aichat_get_context_for_question()
 * @param string $instructions instrucciones del bot (system)
 * @param string|null $system_override para sobreescribir por completo el system prompt
 * @return array messages
 */
function aichat_build_messages( $question, $contexts = [], $instructions = '', $system_override = null, $opts = [] ) {
    $max_ctx_len = isset($opts['context_max_length']) ? max(0, intval($opts['context_max_length'])) : 0;

    $context_text = '';
    foreach ( (array) $contexts as $c ) {
        $title   = isset( $c['title'] ) ? $c['title'] : '';
        $type    = isset( $c['type'] ) ? $c['type'] : '';
        $content = isset( $c['content'] ) ? $c['content'] : '';
        $chunk   = "--- {$title}" . ( $type ? " ({$type})" : '' ) . "\n{$content}\n\n";

        if ($max_ctx_len > 0) {
            $remain = $max_ctx_len - strlen($context_text);
            if ($remain <= 0) break;
            $context_text .= substr($chunk, 0, $remain);
            if (strlen($chunk) > $remain) break;
        } else {
            $context_text .= $chunk;
        }
    }

    $system = $system_override;
    if ( $system === null ) {
        $has_ctx = ($context_text !== '');
        $base = $has_ctx
          ? __( 'You are an assistant that must base answers ONLY on the provided CONTEXT. If the answer is not in context reply: "I cannot find that information in the context." Do not fabricate.', 'aichat' )
          : __( 'You are a helpful assistant. Answer clearly and concisely. If you do not know, say you do not know.', 'aichat' );
        $system = trim( $instructions . "\n\n" . $base );
    }

    $user = ($context_text !== '')
      ? sprintf(
      "CONTEXT:\n%s\nQUESTION:\n%s\n\n%s",
      $context_text,
      $question,
      __( 'If the answer needs to link to a post from context, include the marker [LINK] where appropriate.', 'aichat' )
    )
      : (string)$question;

    return [
        [ 'role' => 'system', 'content' => $system ],
        [ 'role' => 'user',   'content' => $user ],
    ];
}

/**
 * Reemplaza [LINK] por el enlace del mejor contexto si existe.
 */
function aichat_replace_link_placeholder( $answer ) {
    if ( strpos( $answer, '[LINK]' ) === false ) {
        return $answer;
    }
    $ctx = $GLOBALS['contexts'] ?? [];
    if ( empty( $ctx ) ) {
        return str_replace( '[LINK]', __( 'Link not available', 'aichat' ), $answer );
    }
    $top = reset( $ctx );
    if ( ! $top ) {
        return str_replace( '[LINK]', __( 'Link not available', 'aichat' ), $answer );
    }
    $post_id  = isset( $top['post_id'] ) ? intval( $top['post_id'] ) : 0;
    $title    = isset( $top['title'] ) ? $top['title'] : '';
    $perma    = $post_id ? get_permalink( $post_id ) : '';
    if ( $perma ) {
        return str_replace( '[LINK]', '<a href="' . esc_url( $perma ) . '" target="_blank" rel="noopener">' . esc_html( $title ) . '</a>', $answer );
    }
    return str_replace( '[LINK]', __( 'Link not available', 'aichat' ), $answer );
}

/**
 * (Legacy) Wrapper anterior. Mantener por compatibilidad.
 * Mejor usar: aichat_get_context_for_question() + aichat_build_messages() + llamada de proveedor.
 */
function aichat_get_response( $question ) {
    $messages = aichat_build_messages( $question, aichat_get_context_for_question( $question, [ 'mode' => 'auto', 'limit' => 5 ] ) );
    $api_key  = get_option( 'aichat_openai_api_key' );
    if ( empty( $api_key ) ) {
        return 'Error: falta OpenAI API Key.';
    }

    $response = wp_remote_post( 'https://api.openai.com/v1/chat/completions', [
        'headers' => [ 'Authorization' => 'Bearer ' . $api_key, 'Content-Type' => 'application/json' ],
        'body'    => wp_json_encode( [ 'model' => 'gpt-4o-mini', 'temperature' => 0.2, 'messages' => $messages ] ),
        'timeout' => 40,
    ] );

    if ( is_wp_error( $response ) ) {
        return 'Error: ' . $response->get_error_message();
    }

    $data = json_decode( wp_remote_retrieve_body( $response ), true );
    $raw  = $data['choices'][0]['message']['content'] ?? 'Error generando respuesta';
    return aichat_replace_link_placeholder( $raw );
}

/**
 * Sanitizar remote_endpoint para Pinecone.
 *
 * - Quita espacios.
 * - Escapa URL.
 * - Requiere esquema y host.
 * - Solo permite HTTPS.
 * - Allowlist de dominios (ej. pinecone.io).
 * - Quita path potencialmente peligroso.
 *
 * @param string $url
 * @return string URL sanitizada o vacía si no es válida
 */
if ( ! function_exists('aichat_sanitize_remote_endpoint') ) {
    function aichat_sanitize_remote_endpoint( $url ) {
        $url = trim( (string)$url );
        if ( $url === '' ) return '';
        $url = esc_url_raw( $url );
        // Usar wp_parse_url para consistencia (evita diferencias entre versiones de PHP)
        $p = wp_parse_url( $url );
        if ( ! is_array( $p ) || empty( $p['scheme'] ) || empty( $p['host'] ) ) {
            return '';
        }
        if ( strtolower( $p['scheme'] ) !== 'https' ) {
            return '';
        }
        // Allowlist (extensible): solo dominios pinecone + propios definidos en filtro
        $host = strtolower($p['host']);
        $allowed = apply_filters( 'aichat_remote_endpoint_allowed_hosts', [
            // Ejemplos Pinecone (*.pinecone.io)
            'pinecone.io',
        ] );
        $ok = false;
        foreach ( $allowed as $allow ) {
            $allow = ltrim(strtolower($allow), '.');
            if ( $host === $allow || str_ends_with($host, '.'.$allow) ) {
                $ok = true; break;
            }
        }
        if ( ! $ok ) return '';
        // Quitar path potencialmente peligroso (nos quedamos con base)
        $base = $p['scheme'].'://'.$host;
        return $base;
    }
}