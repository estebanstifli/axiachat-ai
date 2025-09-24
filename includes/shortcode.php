<?php
if ( ! defined( 'ABSPATH' ) ) { exit; }

/**
 * [aichat] shortcode
 * Uso:
 *   [aichat bot="mi-bot-slug"]
 *   (alias) [aichat id="mi-bot-slug"]
 */
add_action( 'init', function() {
    add_shortcode( 'aichat', 'aichat_render_shortcode' );
} );

function aichat_render_shortcode( $atts, $content = null, $tag = 'aichat' ) {
    global $wpdb;

    // Impide que se pinte el Global en esta página
    $GLOBALS['aichat_has_shortcode'] = true;

    // Atributos de entrada
    $atts = shortcode_atts( [
        'bot'         => '',
        'id'          => '',
        // Overrides opcionales (si los pasas en el shortcode, prevalecen sobre la BD)
        'title'       => '',
        'placeholder' => '',
        'class'       => '',
        // NUEVO: permitir overrides para unificar con el global
        'layout'      => '',   // 'floating' | 'inline'
        'position'    => '',   // 'br'|'bl'|'tr'|'tl' o equivalentes normales
    ], $atts, $tag );

    // Resolver slug
    $slug = sanitize_title( $atts['bot'] ?: $atts['id'] );

    if ( empty( $slug ) ) {
        // Fallbacks: global → primero
        $global_on   = (bool) get_option( 'aichat_global_bot_enabled', false );
        $global_slug = $global_on ? get_option( 'aichat_global_bot_slug', '' ) : '';
        if ( $global_on && $global_slug ) {
            $slug = sanitize_title( $global_slug );
        } else {
            $slug = $wpdb->get_var( "SELECT slug FROM {$wpdb->prefix}aichat_bots ORDER BY id ASC LIMIT 1" );
            $slug = $slug ? sanitize_title( $slug ) : '';
        }
    }

    // Si seguimos sin slug, avisa (solo admin)
    if ( empty( $slug ) ) {
        if ( current_user_can('manage_options') ) {
            return '<div class="aichat-widget"><em style="color:#b00">' . esc_html__( '[AIChat] No bots configured.', 'ai-chat' ) . '</em></div>';
        }
        return '<div class="aichat-widget"></div>';
    }

    // Leer bot de BD
    $table = $wpdb->prefix . 'aichat_bots';
    $bot   = $wpdb->get_row( $wpdb->prepare("SELECT * FROM {$table} WHERE slug=%s LIMIT 1", $slug), ARRAY_A );

    if ( ! $bot ) {
        if ( current_user_can('manage_options') ) {
            /* translators: %s: bot slug that was not found */
            return '<div class="aichat-widget"><em style="color:#b00">[AIChat] ' . sprintf( esc_html__( 'Bot not found: %s', 'ai-chat' ), esc_html( $slug ) ) . '</em></div>';
        }
        return '<div class="aichat-widget"></div>';
    }

    // --------- Mapear campos de UI desde la tabla de bots ----------
    // Nombres tentativos: usa el primero que exista/no vacío
    $ui_layout = aichat_norm_layout( aichat_pick($bot, ['ui_layout','layout'], 'inline') );
    $ui_pos    = aichat_norm_pos( aichat_pick($bot, ['ui_position','position'], 'bottom-right') );
    
    $ui_color  = aichat_pick($bot, ['ui_color','color','theme_color','primary_color'], '#0073aa');
    $ui_title  = aichat_pick($bot, ['ui_title','title','name'], 'AI Chat');
    $ui_ph     = aichat_pick($bot, ['ui_placeholder','placeholder'], 'Escribe tu pregunta...');
    $ui_width  = intval( aichat_pick($bot, ['ui_width','width'], 320) );
    $ui_height = intval( aichat_pick($bot, ['ui_height','height','messages_height'], 280) );

    // Avatares
    $ui_avatar_enabled = intval( aichat_pick($bot, ['ui_avatar_enabled'], 0) );
    $ui_avatar_key     = aichat_pick($bot, ['ui_avatar_key'], '');
    $ui_icon_url       = aichat_pick($bot, ['ui_icon_url'], '');
    // Controles de ventana
    $ui_closable           = intval( aichat_pick($bot, ['ui_closable'], 1) );
    $ui_minimizable        = intval( aichat_pick($bot, ['ui_minimizable'], 1) );
    $ui_draggable          = intval( aichat_pick($bot, ['ui_draggable'], 1) );
    $ui_minimized_default  = intval( aichat_pick($bot, ['ui_minimized_default'], 0) );
    $ui_superminimized_default = intval( aichat_pick($bot, ['ui_superminimized_default'], 0) );

    // Start sentence
    $ui_start_sentence = aichat_pick($bot, ['ui_start_sentence','start_sentence','ui_start_text','start_text'], '');

    // Botón enviar (nuevo)    
    $ui_button_send = aichat_pick($bot, ['ui_button_send','button_send','ui_send_label'], 'Send');

    // Tipo de bot: 'text' | 'voice_text'
    $bot_type = isset($bot['type']) ? sanitize_text_field($bot['type']) : 'text';

    // URL base del plugin (para assets/images)
    $plugin_base_url = trailingslashit( dirname( plugin_dir_url( __FILE__ ) ) );
    $avatar_url = '';
    if (!empty($ui_icon_url)) {
        $avatar_url = esc_url_raw($ui_icon_url);
    } elseif (preg_match('/^avatar([1-9])$/', (string)$ui_avatar_key)) {
        $avatar_url = $plugin_base_url . 'assets/images/' . $ui_avatar_key . '.png';
    }

    // Overrides por shortcode si vinieran
    if ( !empty($atts['title']) )       $ui_title = sanitize_text_field($atts['title']);
    if ( !empty($atts['placeholder']) ) $ui_ph    = sanitize_text_field($atts['placeholder']);
    // NUEVO: forzar layout/posición si se pasan como atributo (para el global)
    if ( !empty($atts['layout']) )      $ui_layout = aichat_norm_layout($atts['layout']);
    if ( !empty($atts['position']) )    $ui_pos    = aichat_norm_pos($atts['position']);

    // Clases
    $classes = ['aichat-widget'];
    if ( $ui_layout === 'floating' ) $classes[] = 'is-global';
    // normaliza la posición a una clase pos-*
    $pos_class = 'pos-' . str_replace('_','-', strtolower($ui_pos) ); // pos-bottom-right, etc.
    $classes[] = $pos_class;

    if ( !empty($atts['class']) ) {
        $classes[] = preg_replace('/[^a-z0-9\-\_\s]/i','', $atts['class']);
    }

    // Estilo directo opcional (anchura para floating)
    $style = '';
    if ( $ui_layout === 'floating' && $ui_width > 0 ) {
        $style = 'style="width: '.intval($ui_width).'px"';
    }

    // Encola assets
    wp_enqueue_style( 'aichat-frontend' );
    wp_enqueue_script( 'jquery' );
    wp_enqueue_script( 'aichat-frontend' );

    // Localizar opciones GDPR directamente en el script principal (una sola vez)
    static $aichat_gdpr_localized = false;
    if ( ! $aichat_gdpr_localized ) {
        // Usamos aichat_get_setting para que respete el default de register_setting si la opción aún no fue guardada.
        $gdpr_enabled = (int) aichat_get_setting( 'aichat_gdpr_consent_enabled' );
        $gdpr_text_raw   = aichat_get_setting( 'aichat_gdpr_text' );
        $gdpr_button_raw = aichat_get_setting( 'aichat_gdpr_button' );

        // En frontend (no admin_init) los defaults de register_setting no están cargados.
        // Si vienen vacíos, aplicamos los textos por defecto traducibles.
        if ($gdpr_text_raw === '' || $gdpr_text_raw === null || $gdpr_text_raw === false || trim((string)$gdpr_text_raw) === '') {
            $gdpr_text_raw = __( 'By using this chatbot, you agree to the recording and processing of your data for improving our services.', 'ai-chat' );
        }
        if ($gdpr_button_raw === '' || $gdpr_button_raw === null || $gdpr_button_raw === false || trim((string)$gdpr_button_raw) === '') {
            $gdpr_button_raw = __( 'I understand', 'ai-chat' );
        }
        // Sanitizamos para salida JS (el texto permite HTML básico, el botón solo texto plano)
        $gdpr_text    = wp_kses_post( (string) $gdpr_text_raw );
        $gdpr_button  = sanitize_text_field( (string) $gdpr_button_raw );
        // Localizamos sobre el propio handle 'aichat-frontend' para que el objeto exista antes de ejecutar el JS
        wp_localize_script( 'aichat-frontend', 'AIChatGDPR', [
            'enabled' => $gdpr_enabled ? 1 : 0,
            'text'    => $gdpr_text,
            'button'  => $gdpr_button,
            'cookie'  => 'aichat_gdpr_ok'
        ] );
        $aichat_gdpr_localized = true;
    }

    // Contenedor con data-attrs de UI
    $html = sprintf(
        '<div class="%s" %s '.
        'data-bot="%s" data-type="%s" data-title="%s" data-placeholder="%s" '.
        'data-layout="%s" data-position="%s" data-color="%s" '.
        'data-width="%d" data-height="%d" '.
        'data-avatar-enabled="%d" data-avatar-url="%s" '.
        'data-start-sentence="%s" data-button-send="%s" '.
        'data-closable="%d" data-minimizable="%d" data-draggable="%d" data-minimized-default="%d" data-superminimized-default="%d"></div>',
        esc_attr( implode(' ', $classes) ),
        $style,
        esc_attr( $slug ),
        esc_attr( $bot_type ),
        esc_attr( $ui_title ),
        esc_attr( $ui_ph ),
        esc_attr( $ui_layout ),
        esc_attr( strtolower($ui_pos) ),
        esc_attr( $ui_color ),
        $ui_width,
        $ui_height,
        $ui_avatar_enabled ? 1 : 0,
        esc_attr( $avatar_url ),
        esc_attr( $ui_start_sentence ),
        esc_attr( $ui_button_send ),
        $ui_closable ? 1 : 0,
        $ui_minimizable ? 1 : 0,
        $ui_draggable ? 1 : 0,
        $ui_minimized_default ? 1 : 0,
        $ui_superminimized_default ? 1 : 0
    );

    // Honeypot (front-end). Bots that auto-complete will fill this hidden field.
    // Backend rejects any request where $_POST['aichat_hp'] is non-empty.
    $honeypot = '<input type="text" name="aichat_hp" value="" tabindex="-1" autocomplete="off" aria-hidden="true" style="position:absolute!important;left:-9999px!important;top:auto!important;width:1px!important;height:1px!important;overflow:hidden!important;" />';
    $html .= $honeypot;

    return $html;
}

/** helper: toma el primer campo no vacío de un array de claves */
function aichat_pick(array $row, array $keys, $default = '') {
    foreach ($keys as $k) {
        if ( array_key_exists($k, $row) ) {
            $val = $row[$k];
            if ($val === null) continue; // evita null → deprecated en core
            if ($val === '') continue;
            // Normaliza arrays/objetos a cadena corta para evitar notices si se pasan a sanitize_*
            if (is_array($val) || is_object($val)) {
                $val = ''; // no usamos estructuras complejas aquí
            }
            return is_string($val) ? wp_unslash($val) : $val;
        }
    }
    return $default;
}

// helpers globales (puedes ponerlos en un utils.php si prefieres)
function aichat_norm_layout($v){
    $v = strtolower(trim((string)$v));
    if (in_array($v, ['floating','float','global','popup','flotante'], true)) return 'floating';
    if (in_array($v, ['inline','embed','incrustado','contenido','inline-block'], true)) return 'inline';
    return 'inline';
}
function aichat_norm_pos($v){
    $v = strtolower(trim((string)$v));
    if ($v === 'tr') return 'top-right';
    if ($v === 'tl') return 'top-left';
    if ($v === 'br') return 'bottom-right';
    if ($v === 'bl') return 'bottom-left';
    $map = [
        'top-right'    => ['top-right','derecha-superior','superior-derecha'],
        'top-left'     => ['top-left','izquierda-superior','superior-izquierda'],
        'bottom-right' => ['bottom-right','derecha-inferior','inferior-derecha'],
        'bottom-left'  => ['bottom-left','izquierda-inferior','inferior-izquierda'],
    ];
    foreach ($map as $k=>$alts){ if (in_array($v,$alts,true)) return $k; }
    return 'bottom-right';
}