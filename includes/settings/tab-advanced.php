<?php
/**
 * Advanced tab markup for AI Chat settings.
 */
?>
<div class="tab-pane" id="aichat-tab-advanced" role="tabpanel" aria-labelledby="aichat-tab-link-advanced" aria-hidden="true">
    <div class="alert alert-warning d-flex align-items-start mb-4" role="alert">
        <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
        <div>
            <strong><?php echo esc_html__( 'Advanced Settings — Handle with Care', 'axiachat-ai' ); ?></strong>
            <p class="mb-0 mt-2"><?php echo esc_html__( 'These settings control critical bot behavior and security policies. Only modify these values if you understand their impact. Incorrect configuration may compromise bot security or functionality.', 'axiachat-ai' ); ?></p>
        </div>
    </div>
    <div class="row g-4">
        <div class="col-12">
            <div class="card card100 shadow-sm h-100">
                <div class="card-header bg-light d-flex align-items-center">
                    <i class="bi bi-shield-lock-fill me-2"></i><strong><?php echo esc_html__( 'Security & Privacy Policy', 'axiachat-ai' ); ?></strong>
                </div>
                <div class="card-body">
                    <div class="aichat-checkbox-row mb-4">
                        <input type="hidden" name="aichat_datetime_injection_enabled" value="0" />
                        <label for="aichat_datetime_injection_enabled" class="aichat-checkbox-label">
                            <input
                                type="checkbox"
                                id="aichat_datetime_injection_enabled"
                                name="aichat_datetime_injection_enabled"
                                value="1"
                                <?php checked( (int) get_option( 'aichat_datetime_injection_enabled', 1 ), 1 ); ?>
                            />
                            <span><?php echo esc_html__( 'Let the chatbot know the current site date/time', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0 mt-1">
                            <i class="bi bi-clock-history me-1"></i>
                            <?php echo esc_html__( 'When enabled, the system prompt includes the WordPress timezone date/time before every conversation. Disable this to keep the policy static.', 'axiachat-ai' ); ?>
                        </div>
                    </div>
                    <div class="aichat-checkbox-row mb-4">
                        <input type="hidden" name="aichat_inject_user_context_enabled" value="0" />
                        <label for="aichat_inject_user_context_enabled" class="aichat-checkbox-label">
                            <input
                                type="checkbox"
                                id="aichat_inject_user_context_enabled"
                                name="aichat_inject_user_context_enabled"
                                value="1"
                                <?php checked( (int) get_option( 'aichat_inject_user_context_enabled', 0 ), 1 ); ?>
                            />
                            <span><?php echo esc_html__( 'Let the chatbot know if the visitor is logged in', 'axiachat-ai' ); ?></span>
                        </label>
                        <div class="form-text ms-0 mt-1">
                            <i class="bi bi-person-badge me-1"></i>
                            <?php echo esc_html__( 'When enabled, the prompt includes whether the visitor is a logged-in WordPress user and, if so, their user ID. Default is disabled.', 'axiachat-ai' ); ?>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="aichat_security_policy" class="form-label fw-semibold">
                            <?php echo esc_html__( 'Bot Security Policy (System Prompt Prefix)', 'axiachat-ai' ); ?>
                        </label>
                        <textarea
                            name="aichat_security_policy"
                            id="aichat_security_policy"
                            class="form-control font-monospace"
                            rows="5"
                            style="font-size: 0.9em; line-height: 1.6;"
                        ><?php echo esc_textarea( aichat_get_setting( 'aichat_security_policy' ) ); ?></textarea>
                        <p class="form-text mt-2">
                            <i class="bi bi-info-circle me-1"></i>
                            <?php echo esc_html__( 'This policy is automatically prepended to all bot conversations as part of the system prompt. It enforces security rules like preventing API key disclosure, blocking prompt injection attacks, and defining how the bot should respond to sensitive queries.', 'axiachat-ai' ); ?>
                        </p>
                        <p class="form-text text-danger mb-0">
                            <i class="bi bi-exclamation-triangle me-1"></i>
                            <strong><?php echo esc_html__( 'Warning:', 'axiachat-ai' ); ?></strong>
                            <?php echo esc_html__( 'Removing or weakening this policy may expose internal system details, API credentials, or allow malicious prompt injection. Only modify if you have a specific security requirement and understand the implications.', 'axiachat-ai' ); ?>
                        </p>
                    </div>
                    <button
                        type="button"
                        class="btn btn-sm btn-outline-secondary"
                        id="aichat-reset-security-policy"
                    >
                        <i class="bi bi-arrow-counterclockwise me-1"></i>
                        <?php echo esc_html__( 'Reset to Default Policy', 'axiachat-ai' ); ?>
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>
