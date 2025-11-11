function aichat_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) {
        wp_die( esc_html__( 'You do not have sufficient permissions.', 'axiachat-ai' ) );
    }

    global $wpdb;
    $bots_table = $wpdb->prefix . 'aichat_bots';
    $bots = $wpdb->get_results( "SELECT slug, name FROM {$bots_table} ORDER BY id ASC", ARRAY_A );

    $openai_key  = aichat_get_setting( 'aichat_openai_api_key' );
    $claude_key  = aichat_get_setting( 'aichat_claude_api_key' );
    $gemini_key  = aichat_get_setting( 'aichat_gemini_api_key' );
    $global_on   = (bool) aichat_get_setting( 'aichat_global_bot_enabled' );
    $global_slug = aichat_get_setting( 'aichat_global_bot_slug' );

    ?>
    <div class="wrap aichat-settings-wrap">
        <h1 class="wp-heading-inline"><span class="dashicons dashicons-format-chat" style="color:#2271b1"></span> <?php echo esc_html__( 'AI Chat — Settings', 'axiachat-ai' ); ?></h1>
        <p class="description mb-3"><?php echo esc_html__( 'Configure global behaviour, API keys, logging, consent and moderation.', 'axiachat-ai' ); ?></p>

        <form method="post" action="options.php" class="aichat-settings-form">
            <?php settings_fields( 'aichat_settings' ); ?>

            <div class="aichat-settings-tabs mt-3">
                <ul class="nav nav-tabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link active" id="aichat-tab-link-general" data-tab-target="aichat-tab-general" role="tab" aria-controls="aichat-tab-general" aria-selected="true"><?php echo esc_html__( 'General', 'axiachat-ai' ); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="aichat-tab-link-usage" data-tab-target="aichat-tab-usage" role="tab" aria-controls="aichat-tab-usage" aria-selected="false"><?php echo esc_html__( 'Usage & Moderation', 'axiachat-ai' ); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="aichat-tab-link-gdpr" data-tab-target="aichat-tab-gdpr" role="tab" aria-controls="aichat-tab-gdpr" aria-selected="false"><?php echo esc_html__( 'GDPR Consent', 'axiachat-ai' ); ?></button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button type="button" class="nav-link" id="aichat-tab-link-addons" data-tab-target="aichat-tab-addons" role="tab" aria-controls="aichat-tab-addons" aria-selected="false"><?php echo esc_html__( 'Add-ons', 'axiachat-ai' ); ?></button>
                    </li>
                </ul>
                <style>
                    .aichat-settings-tabs .nav-link { cursor: pointer; }
                    .aichat-settings-tabs .tab-pane { display: none; }
                    .aichat-settings-tabs .tab-pane.active { display: block; }
                </style>

                <div class="tab-content border border-top-0 bg-white p-4">
                    <div class="tab-pane active" id="aichat-tab-general" role="tabpanel" aria-labelledby="aichat-tab-link-general" aria-hidden="false">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-primary text-white d-flex align-items-center">
                                        <i class="bi bi-key-fill me-2"></i><strong><?php echo esc_html__( 'API Keys', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="mb-3">
                                            <label for="aichat_openai_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'OpenAI API Key', 'axiachat-ai' ); ?></label>
                                            <div class="input-group">
                                                <input type="password" autocomplete="off" class="form-control" id="aichat_openai_api_key" name="aichat_openai_api_key" value="<?php echo esc_attr( $openai_key ); ?>" />
                                                <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_openai_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="form-text"><?php echo esc_html__( 'API key to use OpenAI models.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="aichat_claude_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'Claude (Anthropic) API Key', 'axiachat-ai' ); ?></label>
                                            <div class="input-group">
                                                <input type="password" autocomplete="off" class="form-control" id="aichat_claude_api_key" name="aichat_claude_api_key" value="<?php echo esc_attr( $claude_key ); ?>" />
                                                <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_claude_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="form-text"><?php echo esc_html__( 'API key to use Anthropic (Claude) models.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-0">
                                            <label for="aichat_gemini_api_key" class="form-label fw-semibold"><?php echo esc_html__( 'Google Gemini API Key', 'axiachat-ai' ); ?></label>
                                            <div class="input-group">
                                                <input type="password" autocomplete="off" class="form-control" id="aichat_gemini_api_key" name="aichat_gemini_api_key" value="<?php echo esc_attr( $gemini_key ); ?>" />
                                                <button class="btn btn-outline-secondary aichat-toggle-secret" type="button" data-target="aichat_gemini_api_key" aria-label="Toggle visibility"><i class="bi bi-eye"></i></button>
                                            </div>
                                            <div class="form-text"><?php echo esc_html__( 'API key to use Google Gemini models. Get your key at aistudio.google.com/apikey', 'axiachat-ai' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-secondary text-white d-flex align-items-center">
                                        <i class="bi bi-robot me-2"></i><strong><?php echo esc_html__( 'Global Bot & Logging', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_global_bot_enabled" value="0" />
                                            <label for="aichat_global_bot_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_global_bot_enabled" name="aichat_global_bot_enabled" value="1" <?php checked( $global_on ); ?> />
                                                <span><?php echo esc_html__( 'Enable global floating bot', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'Shortcode [aichat bot="..."] on a page suppresses the global bot there.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="aichat_global_bot_slug" class="form-label fw-semibold"><?php echo esc_html__( 'Global Bot', 'axiachat-ai' ); ?></label>
                                            <?php if ( empty( $bots ) ) : ?>
                                                <select id="aichat_global_bot_slug" class="form-select" disabled name="aichat_global_bot_slug"><option><?php echo esc_html__( 'No bots defined yet', 'axiachat-ai' ); ?></option></select>
                                                <div class="form-text"><?php printf( wp_kses_post( __( 'Create one in <a href="%s">AI Chat → Bots</a>.', 'axiachat-ai' ) ), esc_url( admin_url( 'admin.php?page=aichat-bots-settings' ) ) ); ?></div>
                                            <?php else : ?>
                                                <select id="aichat_global_bot_slug" class="form-select" name="aichat_global_bot_slug">
                                                    <?php foreach ( $bots as $bot ) : ?>
                                                        <option value="<?php echo esc_attr( $bot['slug'] ); ?>" <?php selected( $global_slug, $bot['slug'] ); ?>><?php echo esc_html( $bot['name'] . ' (' . $bot['slug'] . ')' ); ?></option>
                                                    <?php endforeach; ?>
                                                </select>
                                                <div class="form-text"><?php echo esc_html__( 'Bot used when global floating bot is active.', 'axiachat-ai' ); ?></div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="aichat-checkbox-row mb-0">
                                            <input type="hidden" name="aichat_logging_enabled" value="0" />
                                            <label for="aichat_logging_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_logging_enabled" name="aichat_logging_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_logging_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'Conversation logging', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'Disable to stop saving new messages (existing records remain).', 'axiachat-ai' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-success text-white d-flex align-items-center">
                                        <i class="bi bi-globe me-2"></i><strong><?php echo esc_html__( 'Embed (External Sites)', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <?php $embed_origins_raw = (string) get_option( 'aichat_embed_allowed_origins', '' ); ?>
                                        <p class="mb-3 text-muted" style="font-size:13px;">
                                            <?php echo esc_html__( 'List the allowed external site origins (protocol + domain) that can embed the chat via the script loader. One per line. Example: https://example.com', 'axiachat-ai' ); ?>
                                        </p>
                                        <div class="mb-3">
                                            <label for="aichat_embed_allowed_origins" class="form-label fw-semibold"><?php echo esc_html__( 'Allowed Origins', 'axiachat-ai' ); ?></label>
                                            <textarea id="aichat_embed_allowed_origins" name="aichat_embed_allowed_origins" class="form-control" rows="5" placeholder="https://site1.com
https://sub.site2.net"><?php echo esc_textarea( $embed_origins_raw ); ?></textarea>
                                            <div class="form-text"><?php echo esc_html__( 'Leave empty to disallow all external script embeds (iframe method still works).', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-3 small text-secondary">
                                            <?php echo esc_html__( 'Security: Each external request is validated against this list. Use full origin, no trailing slash.', 'axiachat-ai' ); ?>
                                        </div>
                                        <div class="mb-0" style="background:#1e293b;color:#e2e8f0;border:1px solid #334155;border-radius:6px;padding:14px;font-family:Consolas,Menlo,monospace;font-size:12.5px;line-height:1.5;">
<?php $example_bot = $global_slug ? $global_slug : ( ! empty( $bots ) ? $bots[0]['slug'] : 'default' ); ?>
<div style="font-weight:600;margin-bottom:6px;color:#93c5fd;"><?php echo esc_html__( 'Example snippet to paste on an external page', 'axiachat-ai' ); ?>:</div>
&lt;!-- AI Chat Widget --&gt;<br />
&lt;div id=&quot;aichat-embed&quot; data-bot=&quot;<?php echo esc_html( $example_bot ); ?>&quot;&gt;&lt;/div&gt;<br />
&lt;script async src=&quot;<?php echo esc_url( AICHAT_PLUGIN_URL . 'assets/js/aichat-embed-loader.js' ); ?>&quot; data-ajax-url=&quot;<?php echo esc_url( admin_url( 'admin-ajax.php' ) ); ?>&quot; data-nonce-endpoint=&quot;<?php echo esc_url( add_query_arg( 'aichat_embed_nonce', '1', home_url( '/' ) ) ); ?>&quot;&gt;&lt;/script&gt;<br />
&lt;!-- /AI Chat Widget --&gt;
<div style="margin-top:6px;font-size:11px;color:#cbd5e1;">
<?php echo wp_kses_post( __( 'Make sure the external site origin (e.g. <code>https://example.com</code>) is present in the list above, otherwise the embed will be blocked.', 'axiachat-ai' ) ); ?>
</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="aichat-tab-usage" role="tabpanel" aria-labelledby="aichat-tab-link-usage" aria-hidden="true">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-dark text-white d-flex align-items-center">
                                        <i class="bi bi-speedometer2 me-2"></i><strong><?php echo esc_html__( 'Usage (Limits)', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <?php $logging_on = (bool) aichat_get_setting( 'aichat_logging_enabled' ); ?>
                                        <?php if ( ! $logging_on ) : ?>
                                            <div class="alert alert-warning p-2 py-2 mb-3"><i class="bi bi-exclamation-triangle-fill me-1"></i> <?php echo esc_html__( 'Conversation logging must be enabled for limits to work.', 'axiachat-ai' ); ?></div>
                                        <?php endif; ?>
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_usage_limits_enabled" value="0" />
                                            <label for="aichat_usage_limits_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_usage_limits_enabled" name="aichat_usage_limits_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_usage_limits_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'Enable usage limits', 'axiachat-ai' ); ?></span>
                                            </label>
                                        </div>
                                        <div class="row g-3">
                                            <div class="col-md-6">
                                                <label for="aichat_usage_max_daily_total" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per day', 'axiachat-ai' ); ?></label>
                                                <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_total" name="aichat_usage_max_daily_total" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_max_daily_total' ) ); ?>" />
                                                <div class="form-text"><?php echo esc_html__( '0 = Unlimited', 'axiachat-ai' ); ?></div>
                                            </div>
                                            <div class="col-md-6">
                                                <label for="aichat_usage_max_daily_per_user" class="form-label fw-semibold"><?php echo esc_html__( 'Max messages per user/day', 'axiachat-ai' ); ?></label>
                                                <input type="number" min="0" class="form-control" id="aichat_usage_max_daily_per_user" name="aichat_usage_max_daily_per_user" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_max_daily_per_user' ) ); ?>" />
                                                <div class="form-text"><?php echo esc_html__( 'Guests tracked by IP. 0 = Unlimited', 'axiachat-ai' ); ?></div>
                                            </div>
                                        </div>
                                        <hr />
                                        <div class="mb-3">
                                            <label for="aichat_usage_per_user_message" class="form-label fw-semibold"><?php echo esc_html__( 'Message when user limit reached', 'axiachat-ai' ); ?></label>
                                            <input type="text" class="form-control" id="aichat_usage_per_user_message" name="aichat_usage_per_user_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_per_user_message' ) ); ?>" />
                                        </div>
                                        <div class="mb-3">
                                            <label class="form-label fw-semibold" for="aichat_usage_daily_total_behavior"><?php echo esc_html__( 'Daily total limit behavior', 'axiachat-ai' ); ?></label>
                                            <?php $beh = get_option( 'aichat_usage_daily_total_behavior', 'disabled' ); ?>
                                            <select class="form-select" id="aichat_usage_daily_total_behavior" name="aichat_usage_daily_total_behavior">
                                                <option value="disabled" <?php selected( $beh, 'disabled' ); ?>><?php echo esc_html__( 'Show widget disabled with message', 'axiachat-ai' ); ?></option>
                                                <option value="hide" <?php selected( $beh, 'hide' ); ?>><?php echo esc_html__( 'Hide widget completely', 'axiachat-ai' ); ?></option>
                                            </select>
                                        </div>
                                        <div class="mb-0">
                                            <label for="aichat_usage_daily_total_message" class="form-label fw-semibold"><?php echo esc_html__( 'Daily total limit message', 'axiachat-ai' ); ?></label>
                                            <input type="text" class="form-control" id="aichat_usage_daily_total_message" name="aichat_usage_daily_total_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_usage_daily_total_message' ) ); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-warning d-flex align-items-center">
                                        <i class="bi bi-shield-exclamation me-2"></i><strong><?php echo esc_html__( 'Moderation', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="aichat-checkbox-row mb-3">
                                            <label for="aichat_moderation_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_moderation_enabled" name="aichat_moderation_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'Enable moderation layer', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'Checks IP/words and optionally external API before sending to AI.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="aichat-checkbox-row mb-3">
                                            <label for="aichat_moderation_external_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_moderation_external_enabled" name="aichat_moderation_external_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_external_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'External moderation (OpenAI)', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'Requires OpenAI API key (omni-moderation-latest).', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-3">
                                            <label for="aichat_moderation_rejection_message" class="form-label fw-semibold"><?php echo esc_html__( 'Rejection message', 'axiachat-ai' ); ?></label>
                                            <input type="text" class="form-control" id="aichat_moderation_rejection_message" name="aichat_moderation_rejection_message" value="<?php echo esc_attr( aichat_get_setting( 'aichat_moderation_rejection_message' ) ); ?>" />
                                        </div>
                                        <div class="mb-3">
                                            <label for="aichat_moderation_banned_ips" class="form-label fw-semibold"><?php echo esc_html__( 'Blocked IPs', 'axiachat-ai' ); ?></label>
                                            <textarea class="form-control" id="aichat_moderation_banned_ips" name="aichat_moderation_banned_ips" rows="4"><?php echo esc_textarea( get_option( 'aichat_moderation_banned_ips', '' ) ); ?></textarea>
                                            <div class="form-text"><?php echo esc_html__( 'One per line. Supports CIDR.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div>
                                            <label for="aichat_moderation_banned_words" class="form-label fw-semibold d-block"><?php echo esc_html__( 'Banned words', 'axiachat-ai' ); ?></label>
                                            <div class="form-check mb-2">
                                                <label class="aichat-checkbox-label" for="aichat_moderation_use_default_words">
                                                    <input type="checkbox" id="aichat_moderation_use_default_words" name="aichat_moderation_use_default_words" value="1" <?php checked( (int) aichat_get_setting( 'aichat_moderation_use_default_words' ), 1 ); ?> />
                                                    <span><?php echo esc_html__( 'Include base list in English', 'axiachat-ai' ); ?></span>
                                                </label>
                                            </div>
                                            <textarea class="form-control" id="aichat_moderation_banned_words" name="aichat_moderation_banned_words" rows="5"><?php echo esc_textarea( get_option( 'aichat_moderation_banned_words', '' ) ); ?></textarea>
                                            <div class="form-text"><?php echo esc_html__( 'One per line. Regex allowed if wrapped in /.', 'axiachat-ai' ); ?></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="aichat-tab-gdpr" role="tabpanel" aria-labelledby="aichat-tab-link-gdpr" aria-hidden="true">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-info text-white d-flex align-items-center">
                                        <i class="bi bi-shield-lock-fill me-2"></i><strong><?php echo esc_html__( 'GDPR Consent', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_gdpr_consent_enabled" value="0" />
                                            <label for="aichat_gdpr_consent_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_gdpr_consent_enabled" name="aichat_gdpr_consent_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_gdpr_consent_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'Enable consent gate', 'axiachat-ai' ); ?></span>
                                            </label>
                                        </div>
                                        <div class="mb-3">
                                            <label for="aichat_gdpr_text" class="form-label fw-semibold"><?php echo esc_html__( 'Consent text', 'axiachat-ai' ); ?></label>
                                            <input type="text" class="form-control" id="aichat_gdpr_text" name="aichat_gdpr_text" value="<?php echo esc_attr( aichat_get_setting( 'aichat_gdpr_text' ) ); ?>" />
                                            <div class="form-text"><?php echo esc_html__( 'Shown above the accept button. Basic HTML allowed.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <div class="mb-0">
                                            <label for="aichat_gdpr_button" class="form-label fw-semibold"><?php echo esc_html__( 'Button label', 'axiachat-ai' ); ?></label>
                                            <input type="text" class="form-control" id="aichat_gdpr_button" name="aichat_gdpr_button" value="<?php echo esc_attr( aichat_get_setting( 'aichat_gdpr_button' ) ); ?>" />
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="tab-pane" id="aichat-tab-addons" role="tabpanel" aria-labelledby="aichat-tab-link-addons" aria-hidden="true">
                        <div class="row g-4">
                            <div class="col-12">
                                <div class="card shadow-sm h-100">
                                    <div class="card-header bg-purple text-white d-flex align-items-center" style="background:#6f42c1;">
                                        <i class="bi bi-puzzle-fill me-2"></i><strong><?php echo esc_html__( 'Add-ons', 'axiachat-ai' ); ?></strong>
                                    </div>
                                    <div class="card-body">
                                        <div class="aichat-checkbox-row mb-3">
                                            <input type="hidden" name="aichat_addon_ai_tools_enabled" value="0" />
                                            <label for="aichat_addon_ai_tools_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_addon_ai_tools_enabled" name="aichat_addon_ai_tools_enabled" value="1" <?php checked( (int) aichat_get_setting( 'aichat_addon_ai_tools_enabled' ), 1 ); ?> />
                                                <span><?php echo esc_html__( 'Enable AI Tools (tools & macros system)', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0"><?php echo esc_html__( 'When enabled, exposes the AI Tools menus and allows bots to call registered tools/macros.', 'axiachat-ai' ); ?></div>
                                        </div>
                                        <?php $ai_tools_enabled_flag = (int) aichat_get_setting( 'aichat_addon_ai_tools_enabled' ); ?>
                                        <div class="aichat-checkbox-row mb-0">
                                            <input type="hidden" name="aichat_tools_ssa_enabled" value="0" />
                                            <label for="aichat_tools_ssa_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_tools_ssa_enabled" name="aichat_tools_ssa_enabled" value="1" <?php checked( (int) get_option( 'aichat_tools_ssa_enabled', 0 ), 1 ); ?> <?php disabled( ! $ai_tools_enabled_flag ); ?> />
                                                <span><?php echo esc_html__( 'Enable Simply Schedule Appointments (SSA) tools', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0">
                                                <?php
                                                if ( ! $ai_tools_enabled_flag ) {
                                                    echo esc_html__( 'Requires AI Tools enabled. Turn on AI Tools above to activate SSA integration.', 'axiachat-ai' );
                                                } else {
                                                    echo esc_html__( 'Registers tools/macros to list services, check availability and create bookings. Requires the SSA plugin.', 'axiachat-ai' );
                                                }
                                                ?>
                                            </div>
                                        </div>
                                        <hr class="my-3" />
                                        <div class="aichat-checkbox-row mb-0">
                                            <input type="hidden" name="aichat_addon_mcp_enabled" value="0" />
                                            <label for="aichat_addon_mcp_enabled" class="aichat-checkbox-label">
                                                <input type="checkbox" id="aichat_addon_mcp_enabled" name="aichat_addon_mcp_enabled" value="1" <?php checked( (int) get_option( 'aichat_addon_mcp_enabled', 0 ), 1 ); ?> <?php disabled( ! $ai_tools_enabled_flag ); ?> />
                                                <span><?php echo esc_html__( 'Enable MCP (Model Context Protocol)', 'axiachat-ai' ); ?></span>
                                            </label>
                                            <div class="form-text ms-0">
                                                <?php
                                                if ( ! $ai_tools_enabled_flag ) {
                                                    echo esc_html__( 'Requires AI Tools enabled. Turn on AI Tools above to activate MCP integration.', 'axiachat-ai' );
                                                } else {
                                                    echo esc_html__( 'Allows bots to connect to external MCP servers (Sentry, Notion, GitHub, etc.) and expose their tools. Configure servers in AI Chat → MCP Servers.', 'axiachat-ai' );
                                                }
                                                ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card shadow-sm mt-4">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="bi bi-save2 me-2"></i><strong><?php echo esc_html__( 'Save', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <?php submit_button( __( 'Save changes', 'axiachat-ai' ), 'primary', 'submit', false ); ?>
                    <?php if ( $global_on && ( empty( $bots ) || empty( $global_slug ) ) ) : ?>
                        <div class="alert alert-warning mt-3 mb-0"><strong>AI Chat:</strong> <?php echo esc_html__( 'Global Bot is enabled but no bot is selected. On save the first available bot will be used.', 'axiachat-ai' ); ?></div>
                    <?php endif; ?>
                </div>
            </div>
        </form>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        var container = document.querySelector('.aichat-settings-tabs');
        if (!container) {
            return;
        }
        var tabLinks = container.querySelectorAll('.nav-link');
        var tabPanes = container.querySelectorAll('.tab-pane');
        if (!tabLinks.length || !tabPanes.length) {
            return;
        }
        function activateTab(targetId) {
            tabLinks.forEach(function(link) {
                if (link.getAttribute('data-tab-target') === targetId) {
                    link.classList.add('active');
                    link.setAttribute('aria-selected', 'true');
                } else {
                    link.classList.remove('active');
                    link.setAttribute('aria-selected', 'false');
                }
            });
            tabPanes.forEach(function(pane) {
                if (pane.id === targetId) {
                    pane.classList.add('active');
                    pane.setAttribute('aria-hidden', 'false');
                } else {
                    pane.classList.remove('active');
                    pane.setAttribute('aria-hidden', 'true');
                }
            });
        }
        tabLinks.forEach(function(link) {
            link.addEventListener('click', function(event) {
                event.preventDefault();
                var targetId = link.getAttribute('data-tab-target');
                if (targetId) {
                    activateTab(targetId);
                }
            });
        });
        var initialLink = container.querySelector('.nav-link.active');
        if (initialLink) {
            activateTab(initialLink.getAttribute('data-tab-target'));
        } else if (tabPanes[0]) {
            activateTab(tabPanes[0].id);
        }
    });
    </script>
    <?php
}
