<?php
/**
 * AI Chat — Simplified Settings (fixed)
 *
 * @package AIChat
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Helper: retrieve option honoring register_setting default when option not stored yet.
 * get_option( 'x', $fallback ) bypasses the registered default because the fallback always wins.
 * This checks the raw option value first; if it is strictly false (not created) we pull the global
 * $wp_registered_settings structure to extract the configured 'default'.
 */
function aichat_get_setting( $name ) {
    // get_option devuelve false si la opción NO existe todavía.
    // No podemos usar null como centinela porque WP no lo devuelve nunca.
    $val = get_option( $name, '__AICHAT_NO_OPTION__' );
    if ( $val !== '__AICHAT_NO_OPTION__' && $val !== false ) {
        return $val; // Existe (aunque sea cadena vacía o '0').
    }
    // Intentar obtener default registrado (solo disponible si se ejecutó register_setting - normalmente admin_init)
    global $wp_registered_settings;
    if ( isset( $wp_registered_settings[ $name ] ) && array_key_exists( 'default', $wp_registered_settings[ $name ] ) ) {
        return $wp_registered_settings[ $name ]['default'];
    }
    return '';
}

/**
 * Register minimal settings (all in the same group).
 */
add_action( 'admin_init', 'aichat_register_simple_settings' );
function aichat_register_simple_settings() {
    $option_group = 'aichat_settings';    
    register_setting( $option_group, 'aichat_openai_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'aichat_sanitize_api_key',
        'default'           => '',
    ) );
    

    register_setting( $option_group, 'aichat_claude_api_key', array(
        'type'              => 'string',
        'sanitize_callback' => 'aichat_sanitize_api_key',
        'default'           => '',
    ) );
    

    // checkbox: save as 0/1
    register_setting( $option_group, 'aichat_global_bot_enabled', array(
        'type'              => 'boolean',
        'sanitize_callback' => 'aichat_sanitize_checkbox',
        'default'           => true,
    ) );

    // global bot slug
    register_setting( $option_group, 'aichat_global_bot_slug', array(
        'type'              => 'string',
        'sanitize_callback' => 'sanitize_title',
        'default'           => '',
    ) );

    register_setting( $option_group, 'aichat_moderation_enabled', [
        'type'=>'boolean','sanitize_callback'=>'aichat_sanitize_checkbox','default'=>false
    ]);
    register_setting( $option_group, 'aichat_moderation_external_enabled', [
        'type'=>'boolean','sanitize_callback'=>'aichat_sanitize_checkbox','default'=>false
    ]);
    register_setting( $option_group, 'aichat_moderation_use_default_words', [
        'type'=>'boolean','sanitize_callback'=>'aichat_sanitize_checkbox','default'=>true
    ]);
    register_setting( $option_group, 'aichat_moderation_banned_ips', [
        'type'=>'string','sanitize_callback'=>'wp_kses_post','default'=>''
    ]);
    register_setting( $option_group, 'aichat_moderation_banned_words', [
        'type'=>'string','sanitize_callback'=>'wp_kses_post','default'=>''
    ]);
    register_setting( $option_group, 'aichat_moderation_rejection_message', [
        'type'=>'string','sanitize_callback'=>'sanitize_text_field','default'=>'Unauthorized request.'
    ] );
    

    // Nueva opción: logging de conversaciones
    register_setting( $option_group, 'aichat_logging_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'aichat_sanitize_checkbox',
        'default' => 1,
    ] );

    // GDPR consent options
    register_setting( $option_group, 'aichat_gdpr_consent_enabled', [
        'type' => 'boolean',
        'sanitize_callback' => 'aichat_sanitize_checkbox',
        'default' => 0,
    ] );
    register_setting( $option_group, 'aichat_gdpr_text', [
        'type' => 'string',
        'sanitize_callback' => 'wp_kses_post', // permite enlaces o énfasis ligero
    'default' => __( 'By using this chatbot, you agree to the recording and processing of your data for improving our services.', 'ai-chat' ),
    ] );
        register_setting( $option_group, 'aichat_gdpr_button', [
        'type' => 'string',
        'sanitize_callback' => 'sanitize_text_field',
    'default' => __( 'I understand', 'ai-chat' ),
    ] );

        // ========== Usage Limits (nuevo) ==========
        register_setting( $option_group, 'aichat_usage_limits_enabled', [
            'type' => 'boolean',
            'sanitize_callback' => 'aichat_sanitize_checkbox',
            'default' => 1,
        ] );
        register_setting( $option_group, 'aichat_usage_max_daily_total', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 1000, // 0 = sin límite
        ] );
        register_setting( $option_group, 'aichat_usage_max_daily_per_user', [
            'type' => 'integer',
            'sanitize_callback' => 'absint',
            'default' => 30, // 0 = sin límite
        ] );
        register_setting( $option_group, 'aichat_usage_per_user_message', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __( 'I\'m tired, please come back tomorrow.', 'ai-chat' ),
        ] );
        register_setting( $option_group, 'aichat_usage_daily_total_behavior', [
            'type' => 'string',
            'sanitize_callback' => 'aichat_sanitize_daily_total_behavior',
            'default' => 'disabled', // 'hide' | 'disabled'
        ] );
        register_setting( $option_group, 'aichat_usage_daily_total_message', [
            'type' => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default' => __( 'Daily usage limit reached. Please try again tomorrow.', 'ai-chat' ),
        ] );
        
            // Embed allowed origins (array stored as JSON or newline string). We'll store as newline string for simplicity.
            register_setting( $option_group, 'aichat_embed_allowed_origins', [
                'type' => 'string',
                'sanitize_callback' => 'aichat_sanitize_embed_origins',
                'default' => '',
            ] );
        


}

/**
 * Enforce consistency when saving:
 * - checkbox always 0/1
 * - if the global bot is enabled and the slug is empty → assign the first existing bot
 */
add_filter( 'pre_update_option_aichat_global_bot_enabled', function( $new, $old ) {
    return ( ! empty( $new ) && $new !== '0' ) ? 1 : 0;
}, 10, 2 );

add_filter( 'pre_update_option_aichat_global_bot_slug', function( $new, $old ) {
    // Evita deprecations pasando null a funciones internas
    if ($new === null) { $new = ''; }
    $new = sanitize_title( (string)$new );

    // Is the form being saved and the checkbox is active?
    $enabled = isset( $_POST['aichat_global_bot_enabled'] )
        ? (int) $_POST['aichat_global_bot_enabled']
        : (int) get_option( 'aichat_global_bot_enabled', 0 );

    if ( $enabled && $new === '' ) {
        global $wpdb;
        $slug = $wpdb->get_var( "SELECT slug FROM {$wpdb->prefix}aichat_bots ORDER BY id ASC LIMIT 1" );
        if ( $slug ) {
            return sanitize_title( $slug );
        }
    }
    return $new;
}, 10, 2 );

/**
 * Render the settings page.
 */
function aichat_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( esc_html__( 'You do not have sufficient permissions.', 'ai-chat' ) );
    }

    global $wpdb;
    $bots_table = $wpdb->prefix . 'aichat_bots';
    $bots = $wpdb->get_results( "SELECT slug, name FROM {$bots_table} ORDER BY id ASC", ARRAY_A );

    $openai_key  = aichat_get_setting( 'aichat_openai_api_key' );
    $claude_key  = aichat_get_setting( 'aichat_claude_api_key' );
    $global_on   = (bool) aichat_get_setting( 'aichat_global_bot_enabled' );
    $global_slug = aichat_get_setting( 'aichat_global_bot_slug' );

        ?>
        <div class="wrap aichat-settings-wrap">
            <h1 class="wp-heading-inline"><span class="dashicons dashicons-format-chat" style="color:#2271b1"></span> <?php echo esc_html__( 'AI Chat — Settings', 'ai-chat' ); ?></h1>
            <p class="description mb-3"><?php echo esc_html__( 'Configure global behaviour, API keys, logging, consent and moderation.', 'ai-chat' ); ?></p>

            <form method="post" action="options.php" class="aichat-settings-form">
                <?php settings_fields( 'aichat_settings' ); ?>

                <div class="container-fluid px-0">
                    <div class="row g-4">
                        <!-- Columna izquierda -->
                        <div class="col-lg-6">
                            <!-- API Keys -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-primary text-white d-flex align-items-center">
                                    <i class="bi bi-key-fill me-2"></i><strong><?php echo esc_html__( 'API Keys', 'ai-chat' ); ?></strong>
                                </div>
                                <div class="card-body">
                                    <div class="mb-3">
                                        <label for="aichat_openai_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'OpenAI API Key', 'ai-chat' ); ?></label>
                                        <div class="input-group">
                                            <input type="password" autocomplete="off" class="form-control" id="aichat_openai_api_key" name="aichat_openai_api_key" value="<?php echo esc_attr($openai_key); ?>" />
                                            <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_openai_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                                        </div>
                                        <div class="form-text"><?php echo esc_html__( 'API key to use OpenAI models.', 'ai-chat' ); ?></div>
                                    </div>
                                    <div class="mb-0">
                                        <label for="aichat_claude_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'Claude (Anthropic) API Key', 'ai-chat' ); ?></label>
                                        <div class="input-group">
                                            <input type="password" autocomplete="off" class="form-control" id="aichat_claude_api_key" name="aichat_claude_api_key" value="<?php echo esc_attr($claude_key); ?>" />
                                            <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_claude_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                                        </div>
                                        <div class="form-text"><?php echo esc_html__( 'API key to use Anthropic (Claude) models.', 'ai-chat' ); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Global Bot & Logging -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-secondary text-white d-flex align-items-center">
                                    <i class="bi bi-robot me-2"></i><strong><?php echo esc_html__( 'Global Bot & Logging', 'ai-chat' ); ?></strong>
                                </div>
                                <div class="card-body">
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_global_bot_enabled" value="0" />
                                            <label for="aichat_global_bot_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_global_bot_enabled" name="aichat_global_bot_enabled" value="1" <?php checked($global_on); ?> />
                                                <span><?php echo esc_html__( 'Enable global floating bot', 'ai-chat' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'Shortcode [aichat bot="..."] on a page suppresses the global bot there.', 'ai-chat' ); ?></div>
                                        </div>
                                    <div class="mb-3">
                                        <label for="aichat_global_bot_slug" class="form-label fw-semibold"><?php echo esc_html__( 'Global Bot', 'ai-chat' ); ?></label>
                                        <?php if ( empty($bots) ): ?>
                                            <select id="aichat_global_bot_slug" class="form-select" disabled name="aichat_global_bot_slug"><option><?php echo esc_html__( 'No bots defined yet', 'ai-chat'); ?></option></select>
                                            <?php
                                            /* translators: %s: URL to the AI Chat Bots admin settings page */
                                            ?>
                                            <div class="form-text"><?php printf( wp_kses_post( __( 'Create one in <a href="%s">AI Chat → Bots</a>.','ai-chat') ), esc_url( admin_url('admin.php?page=aichat-bots-settings') ) ); ?></div>
                                        <?php else: ?>
                                            <select id="aichat_global_bot_slug" class="form-select" name="aichat_global_bot_slug">
                                                <?php foreach($bots as $bot): ?>
                                                    <option value="<?php echo esc_attr($bot['slug']); ?>" <?php selected($global_slug,$bot['slug']); ?>><?php echo esc_html($bot['name'].' ('.$bot['slug'].')'); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <div class="form-text"><?php echo esc_html__( 'Bot used when global floating bot is active.', 'ai-chat'); ?></div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="aichat-checkbox-row mb-0">
                                        <input type="hidden" name="aichat_logging_enabled" value="0" />
                                        <label for="aichat_logging_enabled" class="aichat-checkbox-label">
                                            <input type="checkbox" id="aichat_logging_enabled" name="aichat_logging_enabled" value="1" <?php checked( (int) aichat_get_setting('aichat_logging_enabled'), 1 ); ?> />
                                            <span><?php echo esc_html__( 'Conversation logging', 'ai-chat' ); ?></span>
                                        </label>
                                        <div class="form-text ms-0"><?php echo esc_html__( 'Disable to stop saving new messages (existing records remain).', 'ai-chat'); ?></div>
                                    </div>
                                </div>
                            </div>

                                <!-- Usage Limits -->
                                <div class="card shadow-sm mb-4">
                                    <div class="card-header bg-dark text-white d-flex align-items-center">
                                        <i class="bi bi-speedometer2 me-2"></i><strong><?php echo esc_html__( 'Usage (Limits)', 'ai-chat'); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <?php $logging_on = (bool) aichat_get_setting('aichat_logging_enabled'); ?>
                                        <?php if ( ! $logging_on ): ?>
                                            <div class="alert alert-warning p-2 py-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo esc_html__( 'Conversation logging must be enabled for limits to work.', 'ai-chat'); ?></div>
                                        <?php endif; ?>
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_usage_limits_enabled" value="0" />
                                            <label for="aichat_usage_limits_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_usage_limits_enabled" name="aichat_usage_limits_enabled" value="1" <?php checked( (int) aichat_get_setting('aichat_usage_limits_enabled'),1 ); ?> />
                                                <span><?php echo esc_html__( 'Enable usage limits', 'ai-chat'); ?></span>
                                            </label>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="aichat_usage_max_daily_total" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per day', 'ai-chat'); ?></label>
                                                <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_total" name="aichat_usage_max_daily_total" value="<?php echo esc_attr( aichat_get_setting('aichat_usage_max_daily_total') ); ?>" />
                                                <div class="form-text"><?php echo esc_html__( '0 = Unlimited', 'ai-chat'); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="aichat_usage_max_daily_per_user" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per user/day', 'ai-chat'); ?></label>
                                                <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_per_user" name="aichat_usage_max_daily_per_user" value="<?php echo esc_attr( aichat_get_setting('aichat_usage_max_daily_per_user') ); ?>" />
                                                <div class="form-text"><?php echo esc_html__( 'Guests tracked by IP. 0 = Unlimited', 'ai-chat'); ?></div>
                                            </div>
                                        </div>
                                        <hr />
                                        <div class="mb-3">
                                            <label for="aichat_usage_per_user_message" class="form-label fw-semibold"><?php echo esc_html__( 'Message when user limit reached', 'ai-chat'); ?></label>
                                            <input type="text" class="form-control" id="aichat_usage_per_user_message" name="aichat_usage_per_user_message" value="<?php echo esc_attr( aichat_get_setting('aichat_usage_per_user_message') ); ?>" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold" for="aichat_usage_daily_total_behavior"><?php echo esc_html__( 'Daily total limit behavior', 'ai-chat'); ?></label>
                                            <?php $beh = get_option('aichat_usage_daily_total_behavior','disabled'); ?>
                                            <select class="form-select" id="aichat_usage_daily_total_behavior" name="aichat_usage_daily_total_behavior">
                                                <option value="disabled" <?php selected($beh,'disabled'); ?>><?php echo esc_html__( 'Show widget disabled with message', 'ai-chat'); ?></option>
                                                <option value="hide" <?php selected($beh,'hide'); ?>><?php echo esc_html__( 'Hide widget completely', 'ai-chat'); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-0">
                                            <label for="aichat_usage_daily_total_message" class="form-label fw-semibold"><?php echo esc_html__( 'Daily total limit message', 'ai-chat'); ?></label>
                                            <input type="text" class="form-control" id="aichat_usage_daily_total_message" name="aichat_usage_daily_total_message" value="<?php echo esc_attr( aichat_get_setting('aichat_usage_daily_total_message') ); ?>" />
                                        </div>
                                    </div>
                                </div>

                            <!-- GDPR -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-info text-white d-flex align-items-center">
                                    <i class="bi bi-shield-lock-fill me-2"></i><strong><?php echo esc_html__( 'GDPR Consent', 'ai-chat' ); ?></strong>
                                </div>
                                <div class="card-body">
                                    <div class="aichat-checkbox-row mb-3">
                                        <input type="hidden" name="aichat_gdpr_consent_enabled" value="0" />
                                        <label for="aichat_gdpr_consent_enabled" class="aichat-checkbox-label">
                                            <input type="checkbox" id="aichat_gdpr_consent_enabled" name="aichat_gdpr_consent_enabled" value="1" <?php checked( (int) aichat_get_setting('aichat_gdpr_consent_enabled'),1 ); ?> />
                                            <span><?php echo esc_html__( 'Enable consent gate', 'ai-chat' ); ?></span>
                                        </label>
                                    </div>
                                    <div class="mb-3">
                                        <label for="aichat_gdpr_text" class="form-label fw-semibold"><?php echo esc_html__( 'Consent text', 'ai-chat' ); ?></label>
                                        <input type="text" class="form-control" id="aichat_gdpr_text" name="aichat_gdpr_text" value="<?php echo esc_attr( aichat_get_setting('aichat_gdpr_text') ); ?>" />
                                        <div class="form-text"><?php echo esc_html__( 'Shown above the accept button. Basic HTML allowed.', 'ai-chat'); ?></div>
                                    </div>
                                    <div class="mb-0">
                                        <label for="aichat_gdpr_button" class="form-label fw-semibold"><?php echo esc_html__( 'Button label', 'ai-chat' ); ?></label>
                                        <input type="text" class="form-control" id="aichat_gdpr_button" name="aichat_gdpr_button" value="<?php echo esc_attr( aichat_get_setting('aichat_gdpr_button') ); ?>" />
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Columna derecha -->
                        <div class="col-lg-6">
                            <!-- Moderation -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-warning d-flex align-items-center">
                                    <i class="bi bi-shield-exclamation me-2"></i><strong><?php echo esc_html__( 'Moderation', 'ai-chat'); ?></strong>
                                </div>
                                <div class="card-body">
                                    <div class="aichat-checkbox-row mb-3">
                                        <label for="aichat_moderation_enabled" class="aichat-checkbox-label">
                                            <input type="checkbox" id="aichat_moderation_enabled" name="aichat_moderation_enabled" value="1" <?php checked( (int) aichat_get_setting('aichat_moderation_enabled'),1 ); ?> />
                                            <span><?php echo esc_html__('Enable moderation layer','ai-chat'); ?></span>
                                        </label>
                                        <div class="form-text ms-0"><?php echo esc_html__('Checks IP/words and optionally external API before sending to AI.','ai-chat'); ?></div>
                                    </div>
                                    <div class="aichat-checkbox-row mb-3">
                                        <label for="aichat_moderation_external_enabled" class="aichat-checkbox-label">
                                            <input type="checkbox" id="aichat_moderation_external_enabled" name="aichat_moderation_external_enabled" value="1" <?php checked( (int) aichat_get_setting('aichat_moderation_external_enabled'),1 ); ?> />
                                            <span><?php echo esc_html__('External moderation (OpenAI)','ai-chat'); ?></span>
                                        </label>
                                        <div class="form-text ms-0"><?php echo esc_html__('Requires OpenAI API key (omni-moderation-latest).','ai-chat'); ?></div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="aichat_moderation_rejection_message" class="form-label fw-semibold"><?php echo esc_html__('Rejection message','ai-chat'); ?></label>
                                        <input type="text" class="form-control" id="aichat_moderation_rejection_message" name="aichat_moderation_rejection_message" value="<?php echo esc_attr( aichat_get_setting('aichat_moderation_rejection_message') ); ?>" />
                                    </div>
                                    <div class="mb-3">
                                        <label for="aichat_moderation_banned_ips" class="form-label fw-semibold"><?php echo esc_html__('Blocked IPs','ai-chat'); ?></label>
                                        <textarea class="form-control" id="aichat_moderation_banned_ips" name="aichat_moderation_banned_ips" rows="4"><?php echo esc_textarea( get_option('aichat_moderation_banned_ips','') ); ?></textarea>
                                        <div class="form-text"><?php echo esc_html__('One per line. Supports CIDR.','ai-chat'); ?></div>
                                    </div>
                                    <div>
                                        <label for="aichat_moderation_banned_words" class="form-label fw-semibold d-block"><?php echo esc_html__('Banned words','ai-chat'); ?></label>
                                        <div class="form-check mb-2">
                                            <label class="aichat-checkbox-label" for="aichat_moderation_use_default_words">
                                                <input type="checkbox" id="aichat_moderation_use_default_words" name="aichat_moderation_use_default_words" value="1" <?php checked( (int) aichat_get_setting('aichat_moderation_use_default_words'),1 ); ?> />
                                                <span><?php echo esc_html__('Include base list in English','ai-chat'); ?></span>
                                            </label>
                                        </div>
                                        <textarea class="form-control" id="aichat_moderation_banned_words" name="aichat_moderation_banned_words" rows="5"><?php echo esc_textarea( get_option('aichat_moderation_banned_words','') ); ?></textarea>
                                        <div class="form-text"><?php echo esc_html__('One per line. Regex allowed if wrapped in /.','ai-chat'); ?></div>
                                    </div>
                                </div>
                            </div>

                            <!-- Embed Allowed Origins (moved before Save) -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-success text-white d-flex align-items-center">
                                    <i class="bi bi-globe me-2"></i><strong><?php echo esc_html__('Embed (External Sites)', 'ai-chat'); ?></strong>
                                </div>
                                <div class="card-body">
                                    <?php $embed_origins_raw = (string) get_option('aichat_embed_allowed_origins',''); ?>
                                    <p class="mb-3 text-muted" style="font-size:13px;">
                                        <?php echo esc_html__( 'List the allowed external site origins (protocol + domain) that can embed the chat via the script loader. One per line. Example: https://example.com', 'ai-chat'); ?>
                                    </p>
                                    <div class="mb-3">
                                        <label for="aichat_embed_allowed_origins" class="form-label fw-semibold"><?php echo esc_html__( 'Allowed Origins', 'ai-chat'); ?></label>
                                        <textarea id="aichat_embed_allowed_origins" name="aichat_embed_allowed_origins" class="form-control" rows="5" placeholder="https://site1.com
https://sub.site2.net"><?php echo esc_textarea($embed_origins_raw); ?></textarea>
                                        <div class="form-text"><?php echo esc_html__( 'Leave empty to disallow all external script embeds (iframe method still works).', 'ai-chat'); ?></div>
                                    </div>
                                    <div class="mb-3 small text-secondary">
                                        <?php echo esc_html__( 'Security: Each external request is validated against this list. Use full origin, no trailing slash.', 'ai-chat'); ?>
                                    </div>
                                    <div class="mb-0" style="background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:12.5px;line-height:1.5;">
<?php $example_bot = $global_slug ? $global_slug : ( !empty($bots) ? $bots[0]['slug'] : 'default' ); ?>
<div style="font-weight:600;margin-bottom:6px;color:#93c5fd;"><?php echo esc_html__('Example snippet to paste on an external page','ai-chat'); ?>:</div>
&lt;!-- AI Chat Widget --&gt;<br />
&lt;div id=&quot;aichat-embed&quot; data-bot=&quot;<?php echo esc_html( $example_bot ); ?>&quot;&gt;&lt;/div&gt;<br />
&lt;script async src=&quot;<?php echo esc_url( site_url('/wp-content/plugins/ai-chat/assets/js/aichat-embed-loader.js') ); ?>&quot;&gt;&lt;/script&gt;<br />
&lt;!-- /AI Chat Widget --&gt;
<div style="margin-top:6px;font-size:11px;color:#cbd5e1;">
<?php echo wp_kses_post( __( 'Make sure the external site origin (e.g. <code>https://example.com</code>) is present in the list above, otherwise the embed will be blocked.', 'ai-chat' ) ); ?>
</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Save -->
                            <div class="card shadow-sm mb-4">
                                <div class="card-header bg-light d-flex align-items-center">
                                    <i class="bi bi-save2 me-2"></i><strong><?php echo esc_html__('Save','ai-chat'); ?></strong>
                                </div>
                                <div class="card-body">
                                    <?php submit_button( __( 'Save changes', 'ai-chat' ), 'primary', 'submit', false ); ?>
                                    <?php if ( $global_on && ( empty($bots) || empty($global_slug) ) ): ?>
                                        <div class="alert alert-warning mt-3 mb-0"><strong>AI Chat:</strong> <?php echo esc_html__( 'Global Bot is enabled but no bot is selected. On save the first available bot will be used.', 'ai-chat'); ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </form>
        </div>        
        <?php
}

/**
 * Sanitizers
 */
function aichat_sanitize_api_key( $value ) {    
    $value = is_string( $value ) ? trim( $value ) : '';    
    return wp_kses( $value, array() );
}

function aichat_sanitize_checkbox( $value ) {
    return ( ! empty( $value ) && ( $value === '1' || $value === 1 || $value === true ) ) ? 1 : 0;
}

if ( ! function_exists('aichat_sanitize_embed_origins') ) {
    function aichat_sanitize_embed_origins( $value ) {
        if (! is_string($value)) return '';
        $lines = preg_split('/\r\n|\r|\n/', trim($value));
        $clean = [];
        foreach($lines as $l){
            $l = trim($l);
            if ($l === '') continue;
            // Must start with http or https
            if (!preg_match('#^https?://#i',$l)) continue;
            // Remove trailing slash
            $l = rtrim($l,'/');
            // Basic URL validation
            $p = wp_parse_url($l);
            if (empty($p['scheme']) || empty($p['host'])) continue;
            $norm = $p['scheme'].'://'.$p['host'];
            if (!empty($p['port'])) $norm .= ':' . (int)$p['port'];
            if (!in_array($norm, $clean, true)) $clean[] = $norm;
        }
        return implode("\n", $clean);
    }
}

// Falta sanitizer para aichat_usage_daily_total_behavior (evita fatal si WP intenta llamarlo)
if ( ! function_exists('aichat_sanitize_daily_total_behavior') ) {
    function aichat_sanitize_daily_total_behavior( $value ) {
        // Valores permitidos: 'disabled', 'hide'
        $value = is_string($value) ? trim($value) : '';
        if ( $value !== 'hide' ) { $value = 'disabled'; }        
        return $value;
    }
}



// Admin notice si faltan API keys
if ( ! function_exists( 'aichat_admin_api_key_notice' ) ) {
    function aichat_admin_api_key_notice() {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        // Solo mostrar si NO hay ninguna de las dos claves
        $openai  = trim( (string) get_option( 'aichat_openai_api_key', '' ) );
        $claude  = trim( (string) get_option( 'aichat_claude_api_key', '' ) );
        if ( $openai !== '' || $claude !== '' ) {
            return;
        }

        $url = admin_url( 'admin.php?page=aichat-settings' );
        ?>
        <div class="notice notice-warning">
            <p>
                <strong><?php echo esc_html__( 'AI Chat', 'ai-chat' ); ?>:</strong>
                <?php
                printf(
                    wp_kses(
                        /* translators: %s: settings page link */
                        __( 'Please add an OpenAI or Claude API key in %s to start using the chatbot.', 'ai-chat' ),
                        [ 'a' => [ 'href' => [], 'target' => [], 'rel' => [] ] ]
                    ),
                    '<a href="' . esc_url( $url ) . '">' . esc_html__( 'AI Chat Settings', 'ai-chat' ) . '</a>'
                );
                ?>
            </p>
        </div>
        <?php
    }
    add_action( 'admin_notices', 'aichat_admin_api_key_notice' );
    add_action( 'network_admin_notices', 'aichat_admin_api_key_notice' );
}
